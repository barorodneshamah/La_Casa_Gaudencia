<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Repository\FoodRepository;
use App\Service\NotificationService;
use App\Service\WebSocketPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/reservations')]
class ReservationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReservationRepository $reservationRepo,
        private FoodRepository $foodRepo,
        private NotificationService $notifications,
        private WebSocketPublisher $ws,
    ) {}

    #[Route('', name: 'reservation_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filter = $request->query->get('filter', 'all');

        $reservations = match($filter) {
            'pending'    => $this->reservationRepo->findByStatus(Reservation::STATUS_PENDING),
            'confirmed'  => $this->reservationRepo->findByStatus(Reservation::STATUS_CONFIRMED),
            'cancelled'  => $this->reservationRepo->findByStatus(Reservation::STATUS_CANCELLED),
            'completed'  => $this->reservationRepo->findByStatus(Reservation::STATUS_COMPLETED),
            'paid_pending' => $this->reservationRepo->findPaidPending(),
            default      => $this->reservationRepo->findAllWithDetails(),
        };

        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations,
            'filter'       => $filter,
        ]);
    }

    #[Route('/{id}', name: 'reservation_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $reservation = $this->reservationRepo->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Reservation not found.');
        }

        $foodDetails = $this->getFoodDetails($reservation);

        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
            'foodDetails' => $foodDetails,
        ]);
    }

    #[Route('/{id}/mark-paid', name: 'reservation_mark_paid', methods: ['POST'])]
    public function markPaid(Request $request, int $id): Response
    {
        $reservation = $this->reservationRepo->find($id);

        if (!$reservation || !$this->isCsrfTokenValid('paid' . $id, $request->request->get('_token'))) {
            return $this->redirectToRoute('reservation_show', ['id' => $id]);
        }

        $method = $request->request->get('payment_method', Payment::METHOD_CASH);
        $amount = $request->request->get('amount', $reservation->getRemainingBalance());
        $reference = $request->request->get('reference_number');
        $notes = $request->request->get('admin_notes');

        $payment = new Payment();
        $payment->setReservation($reservation);
        $payment->setPaidBy($reservation->getGuest());
        $payment->setAmount((string) $amount);
        $payment->setPaymentMethod($method);
        $payment->setReferenceNumber($reference ?: 'N/A');
        $payment->setAdminNotes($notes);
        $payment->setStatus(Payment::STATUS_APPROVED);
        $payment->setApprovedBy($this->getUser());
        $payment->setApprovedAt(new \DateTime());

        $this->em->persist($payment);

        $reservation->updatePaymentStatus();
        if ($reservation->getPaymentStatus() === Reservation::PAYMENT_PAID) {
            if ($reservation->isPending()) {
                $reservation->setStatus(Reservation::STATUS_CONFIRMED);
            }
        }

        $this->em->flush();
        $this->addFlash('success', 'Payment recorded successfully!');

        return $this->redirectToRoute('reservation_show', ['id' => $id]);
    }

    #[Route('/{id}/approve', name: 'reservation_approve', methods: ['POST'])]
    public function approve(Request $request, int $id): Response
    {
        $reservation = $this->reservationRepo->find($id);

        if ($reservation && $this->isCsrfTokenValid('approve' . $id, $request->request->get('_token'))) {
            $reservation->setStatus(Reservation::STATUS_CONFIRMED);
            $reservation->setApprovedBy($this->getUser());
            $reservation->setApprovedAt(new \DateTime());
            $reservation->setAdminNotes($request->request->get('notes'));

            // If this is an extension request, update the original reservation's dates
            if ($reservation->isExtension()) {
                $original = $reservation->getExtensionOf();
                if ($original) {
                    if ($reservation->getServiceType() === Reservation::SERVICE_ROOM && $reservation->getCheckOutDate()) {
                        $original->setCheckOutDate($reservation->getCheckOutDate());
                    } elseif ($reservation->getServiceType() === Reservation::SERVICE_TOUR && $reservation->getTourDate()) {
                        $original->setTourDate($reservation->getTourDate());
                        if ($reservation->getTourParticipants()) {
                            $original->setTourParticipants($reservation->getTourParticipants());
                        }
                    } elseif ($reservation->getServiceType() === Reservation::SERVICE_SPA && $reservation->getTourDate()) {
                        $original->setTourDate($reservation->getTourDate());
                    }
                }
            }

            $this->em->flush();
            $this->addFlash('success', 'Reservation APPROVED successfully!');

            $notifBody = $reservation->isExtension()
                ? 'Your extension request ' . ($reservation->getReservationCode() ? '#' . $reservation->getReservationCode() : '') . ' has been approved! Your booking dates have been updated.'
                : 'Your reservation ' . ($reservation->getReservationCode() ? '#' . $reservation->getReservationCode() : '') . ' has been confirmed. We look forward to seeing you!';

            $this->pushNotification($reservation, '✅ Booking Confirmed!', $notifBody);
        }

        return $this->redirectToRoute('reservation_show', ['id' => $id]);
    }

    #[Route('/{id}/reject', name: 'reservation_reject', methods: ['POST'])]
    public function reject(Request $request, int $id): Response
    {
        $reservation = $this->reservationRepo->find($id);

        if ($reservation && $this->isCsrfTokenValid('reject' . $id, $request->request->get('_token'))) {
            $reservation->setStatus(Reservation::STATUS_CANCELLED);
            $reservation->setAdminNotes($request->request->get('reason'));
            $this->em->flush();
            $this->addFlash('warning', 'Reservation has been REJECTED.');

            $this->pushNotification(
                $reservation,
                '❌ Booking Cancelled',
                'Your reservation ' . ($reservation->getReservationCode() ? '#' . $reservation->getReservationCode() : '') . ' could not be confirmed. Please contact us for details.'
            );
        }

        return $this->redirectToRoute('reservation_show', ['id' => $id]);
    }

    #[Route('/{id}/extend', name: 'reservation_extend', methods: ['POST'])]
    public function extend(Request $request, int $id): Response
    {
        $reservation = $this->reservationRepo->find($id);

        if (!$reservation || !$this->isCsrfTokenValid('extend' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid request.');
            return $this->redirectToRoute('reservation_show', ['id' => $id]);
        }

        if ($reservation->getStatus() === Reservation::STATUS_CANCELLED) {
            $this->addFlash('error', 'Cancelled reservations cannot be extended or rescheduled.');
            return $this->redirectToRoute('reservation_show', ['id' => $id]);
        }

        $extended = false;

        if ($reservation->getRoom()) {
            if ($request->request->get('new_checkin')) {
                $reservation->setCheckInDate(new \DateTime($request->request->get('new_checkin')));
                $extended = true;
            }
            if ($request->request->get('new_checkout')) {
                $newCheckout = new \DateTime($request->request->get('new_checkout'));
                $reservation->setCheckOutDate($newCheckout);
                $extended = true;
            }
        }

        if ($reservation->getTour()) {
            if ($request->request->get('new_tour_date')) {
                $reservation->setTourDate(new \DateTime($request->request->get('new_tour_date')));
                $extended = true;
            }
            if ($request->request->get('new_participants')) {
                $reservation->setTourParticipants((int) $request->request->get('new_participants'));
                $extended = true;
            }
        }

        if ($reservation->getSpa() && $request->request->get('new_spa_date')) {
            $reservation->setTourDate(new \DateTime($request->request->get('new_spa_date')));
            $extended = true;
        }

        // Generic fallback: any date field submitted counts
        if (!$extended && $request->request->get('new_tour_date')) {
            $reservation->setTourDate(new \DateTime($request->request->get('new_tour_date')));
            $extended = true;
        }

        if (!$extended) {
            $this->addFlash('error', 'No valid date data provided. Please fill in at least one date field.');
            return $this->redirectToRoute('reservation_show', ['id' => $id]);
        }

        if ($notes = $request->request->get('extend_notes')) {
            $reservation->setAdminNotes(($reservation->getAdminNotes() ? $reservation->getAdminNotes() . "\n" : '') . '[Extended] ' . $notes);
        }

        $this->em->flush();
        $this->addFlash('success', 'Reservation dates extended successfully!');

        $this->pushNotification(
            $reservation,
            '📅 Reservation Extended',
            'Your reservation ' . $reservation->getReservationCode() . ' has been extended. Please check your updated booking details.'
        );

        return $this->redirectToRoute('reservation_show', ['id' => $id]);
    }

    #[Route('/{id}/accept-reschedule', name: 'reservation_accept_reschedule', methods: ['POST'])]
    public function acceptReschedule(Request $request, int $id): Response
    {
        $reservation = $this->reservationRepo->find($id);

        if ($reservation && $this->isCsrfTokenValid('accept_reschedule' . $id, $request->request->get('_token'))) {
            $reservation->setStatus(Reservation::STATUS_CONFIRMED);
            $reservation->setApprovedBy($this->getUser());
            $reservation->setApprovedAt(new \DateTime());
            $notes = $request->request->get('notes');
            if ($notes) {
                $reservation->setAdminNotes($notes);
            }
            $this->em->flush();
            $this->addFlash('success', 'Reschedule request ACCEPTED. Reservation is now confirmed with the new dates.');

            $code = $reservation->getReservationCode() ? '#' . $reservation->getReservationCode() : '';
            $this->pushNotification(
                $reservation,
                '✅ Reschedule Confirmed!',
                "Your reschedule request {$code} has been accepted. Your new booking dates are confirmed!"
            );
        }

        return $this->redirectToRoute('reservation_show', ['id' => $id]);
    }

    #[Route('/{id}/decline-reschedule', name: 'reservation_decline_reschedule', methods: ['POST'])]
    public function declineReschedule(Request $request, int $id): Response
    {
        $reservation = $this->reservationRepo->find($id);

        if ($reservation && $this->isCsrfTokenValid('decline_reschedule' . $id, $request->request->get('_token'))) {
            $reservation->setStatus(Reservation::STATUS_CANCELLED);
            $reason = $request->request->get('reason', 'Reschedule request was not approved.');
            $reservation->setAdminNotes($reason);
            $this->em->flush();
            $this->addFlash('warning', 'Reschedule request DECLINED. Reservation has been cancelled.');

            $code = $reservation->getReservationCode() ? '#' . $reservation->getReservationCode() : '';
            $this->pushNotification(
                $reservation,
                '❌ Reschedule Declined',
                "Your reschedule request {$code} was not approved. Please contact us for assistance."
            );
        }

        return $this->redirectToRoute('reservation_show', ['id' => $id]);
    }

    #[Route('/{id}/complete', name: 'reservation_complete', methods: ['POST'])]
    public function complete(Request $request, int $id): Response
    {
        $reservation = $this->reservationRepo->find($id);

        if ($reservation && $this->isCsrfTokenValid('complete' . $id, $request->request->get('_token'))) {
            $reservation->setStatus(Reservation::STATUS_COMPLETED);
            $this->em->flush();
            $this->addFlash('success', 'Reservation marked as COMPLETED!');
        }

        return $this->redirectToRoute('reservation_show', ['id' => $id]);
    }

    private function pushNotification(Reservation $reservation, string $title, string $body): void
    {
        $guest = $reservation->getGuest();
        if (!$guest) return;

        // WebSocket push — reaches the mobile app in real time
        try {
            $this->ws->pushToUser(
                $guest->getUsername(),
                $title,
                $body,
                'reservation',
                ['reservationId' => (string) $reservation->getId()],
            );
        } catch (\Throwable) {}

        // FCM fallback (only fires if the guest has a Firebase token stored)
        try {
            if ($guest->getFcmToken()) {
                $this->notifications->sendToDevice(
                    $guest->getFcmToken(),
                    $title,
                    $body,
                    ['reservationId' => (string) $reservation->getId()]
                );
            }
        } catch (\Throwable) {}
    }

    private function getFoodDetails(Reservation $reservation): array
    {
        $details = [];

        foreach ($reservation->getFoodItems() ?? [] as $foodId => $qty) {
            $food = $this->foodRepo->find($foodId);
            if ($food) {
                $details[] = [
                    'food'     => $food,
                    'quantity' => $qty,
                    'subtotal' => (float) $food->getPrice() * $qty,
                ];
            }
        }

        return $details;
    }
}
