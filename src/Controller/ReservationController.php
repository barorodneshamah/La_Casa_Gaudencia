<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Repository\FoodRepository;
use App\Service\NotificationService;
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
        private NotificationService $notifications
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
            $this->em->flush();
            $this->addFlash('success', 'Reservation APPROVED successfully!');

            $this->pushNotification(
                $reservation,
                '✅ Booking Confirmed!',
                'Your reservation ' . ($reservation->getReservationCode() ? '#' . $reservation->getReservationCode() : '') . ' has been confirmed. We look forward to seeing you!'
            );
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
        try {
            $guest = $reservation->getGuest();
            if ($guest && $guest->getFcmToken()) {
                $this->notifications->sendToDevice(
                    $guest->getFcmToken(),
                    $title,
                    $body,
                    ['reservationId' => (string) $reservation->getId()]
                );
            }
        } catch (\Throwable) {
        }
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
