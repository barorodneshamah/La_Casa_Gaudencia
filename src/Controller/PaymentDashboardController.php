<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Reservation;
use App\Repository\PaymentRepository;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use App\Service\WebSocketPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/payments')]
class PaymentDashboardController extends AbstractController
{
    public function __construct(private WebSocketPublisher $ws) {}

    #[Route('', name: 'app_payment_dashboard', methods: ['GET'])]
    public function index(
        PaymentRepository $paymentRepository,
        ReservationRepository $reservationRepository
    ): Response {
        // Get counts by service type
        $reservationCounts = $reservationRepository->getCountByServiceType();
        $pendingPaymentCounts = $paymentRepository->getPendingCountByServiceType();
        $revenueByService = $paymentRepository->getApprovedAmountByServiceType();

        // Overall statistics
        $totalRevenue = $paymentRepository->getTotalApprovedAmount();
        $todayRevenue = $paymentRepository->getTodayApprovedAmount();
        $pendingAmount = $paymentRepository->getTotalPendingAmount();
        $pendingCount = $paymentRepository->countPending();

        // Service cards data
        $services = [
            [
                'type' => Reservation::SERVICE_ROOM,
                'label' => 'Room Reservations',
                'icon' => 'fa-bed',
                'color' => '#7b1fa2',
                'bgColor' => '#f3e5f5',
            ],
            [
                'type' => Reservation::SERVICE_TOUR,
                'label' => 'Tour Bookings',
                'icon' => 'fa-map-marked-alt',
                'color' => '#2e7d32',
                'bgColor' => '#e8f5e9',
            ],
            [
                'type' => Reservation::SERVICE_PACKAGE,
                'label' => 'Package Deals',
                'icon' => 'fa-box',
                'color' => '#ef6c00',
                'bgColor' => '#fff3e0',
            ],
            [
                'type' => Reservation::SERVICE_FOOD,
                'label' => 'Food Reservations',
                'icon' => 'fa-utensils',
                'color' => '#c62828',
                'bgColor' => '#ffebee',
            ],
          //  [
          //      'type' => Reservation::SERVICE_ITINERARY,
          //      'label' => 'Custom Itineraries',
          //      'icon' => 'fa-route',
          //      'color' => '#00838f',
          //      'bgColor' => '#e0f7fa',
         //   ],
        ];

        // Add counts to each service
        foreach ($services as &$service) {
            $service['reservations'] = $reservationCounts[$service['type']] ?? 0;
            $service['pending'] = $pendingPaymentCounts[$service['type']] ?? 0;
            $service['revenue'] = $revenueByService[$service['type']] ?? 0;
        }

        return $this->render('payment_dashboard/index.html.twig', [
            'services' => $services,
            'totalRevenue' => $totalRevenue,
            'todayRevenue' => $todayRevenue,
            'pendingAmount' => $pendingAmount,
            'pendingCount' => $pendingCount,
        ]);
    }

    #[Route('/service/{serviceType}', name: 'app_payment_by_service', methods: ['GET'])]
    public function byService(
        string $serviceType,
        Request $request,
        PaymentRepository $paymentRepository,
        ReservationRepository $reservationRepository
    ): Response {
        $status = $request->query->get('status');
        
        $payments = $paymentRepository->findByServiceType($serviceType, $status);
        $reservations = $reservationRepository->findByServiceType($serviceType);

        $serviceLabels = [
            Reservation::SERVICE_ROOM => 'Room Reservations',
            Reservation::SERVICE_TOUR => 'Tour Bookings',
             Reservation::SERVICE_PACKAGE => 'Package Deals',
            Reservation::SERVICE_FOOD => 'Food Reservations',
          //  Reservation::SERVICE_ITINERARY => 'Custom Itineraries',
        ];

        return $this->render('payment_dashboard/service_payments.html.twig', [
            'payments' => $payments,
            'reservations' => $reservations,
            'serviceType' => $serviceType,
            'serviceLabel' => $serviceLabels[$serviceType] ?? $serviceType,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/api/stats', name: 'app_payment_stats_api', methods: ['GET'])]
    public function statsApi(
        PaymentRepository $paymentRepository,
        ReservationRepository $reservationRepository
    ): JsonResponse {
        return new JsonResponse([
            'totalRevenue'      => $paymentRepository->getTotalApprovedAmount(),
            'todayRevenue'      => $paymentRepository->getTodayApprovedAmount(),
            'pendingAmount'     => $paymentRepository->getTotalPendingAmount(),
            'pendingCount'      => $paymentRepository->countPending(),
            'revenueByService'  => $paymentRepository->getApprovedAmountByServiceType(),
            'pendingByService'  => $paymentRepository->getPendingCountByServiceType(),
            'reservationCounts' => $reservationRepository->getCountByServiceType(),
        ]);
    }

    #[Route('/payment/{id}', name: 'app_payment_show', methods: ['GET'])]
    public function show(Payment $payment): Response
    {
        return $this->render('payment_dashboard/show.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/payment/{id}/approve', name: 'app_payment_approve', methods: ['POST'])]
    public function approve(
        Request $request,
        Payment $payment,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Only admin and staff can approve payments.');
        }
        if ($this->isCsrfTokenValid('approve'.$payment->getId(), $request->request->get('_token'))) {
            $payment->setStatus(Payment::STATUS_APPROVED);
            $payment->setApprovedBy($this->getUser());
            $payment->setApprovedAt(new \DateTime());
            $payment->setAdminNotes($request->request->get('admin_notes'));

            $reservation = $payment->getReservation();
            $reservation->updatePaymentStatus();

            if ($reservation->getPaymentStatus() === Reservation::PAYMENT_PAID) {
                $reservation->setStatus(Reservation::STATUS_CONFIRMED);
            }

            $entityManager->flush();

            $this->pushToGuest($payment, $notificationService,
                'Payment Approved',
                "Your payment of ₱{$payment->getAmount()} has been approved.",
                'payment_approved'
            );

            $this->addFlash('success', 'Payment approved successfully!');
        }

        return $this->redirectToRoute('app_payment_show', ['id' => $payment->getId()]);
    }

    #[Route('/payment/{id}/reject', name: 'app_payment_reject', methods: ['POST'])]
    public function reject(
        Request $request,
        Payment $payment,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Only admin and staff can reject payments.');
        }
        if ($this->isCsrfTokenValid('reject'.$payment->getId(), $request->request->get('_token'))) {
            $payment->setStatus(Payment::STATUS_REJECTED);
            $payment->setApprovedBy($this->getUser());
            $payment->setApprovedAt(new \DateTime());
            $payment->setRejectionReason($request->request->get('rejection_reason'));
            $payment->setAdminNotes($request->request->get('admin_notes'));

            $entityManager->flush();

            $this->pushToGuest($payment, $notificationService,
                'Payment Rejected',
                "Your payment of ₱{$payment->getAmount()} was rejected. Please contact us for details.",
                'payment_rejected'
            );

            $this->addFlash('success', 'Payment rejected.');
        }

        return $this->redirectToRoute('app_payment_show', ['id' => $payment->getId()]);
    }

    private function pushToGuest(Payment $payment, NotificationService $notificationService, string $title, string $body, string $type): void
    {
        $guest = $payment->getPaidBy();
        if (!$guest) return;

        $data = ['type' => $type, 'paymentId' => (string) $payment->getId(), 'transactionRef' => $payment->getTransactionReference()];

        // WebSocket push — reaches the mobile app in real time
        try {
            $this->ws->pushToUser($guest->getUsername(), $title, $body, 'payment', $data);
        } catch (\Throwable) {}

        // FCM fallback
        try {
            if ($guest->getFcmToken()) {
                $notificationService->sendToDevice($guest->getFcmToken(), $title, $body, $data, 'payment_' . $payment->getId());
            }
        } catch (\Throwable) {}
    }

    #[Route('/payment/{id}/refund', name: 'app_payment_refund', methods: ['POST'])]
    public function refund(
        Request $request,
        Payment $payment,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Only admin and staff can refund payments.');
        }
        if ($this->isCsrfTokenValid('refund'.$payment->getId(), $request->request->get('_token'))) {
            $message = $this->refundPaymentAmount($payment, $request->request->get('admin_notes'), $entityManager);

            // Update reservation
            $reservation = $payment->getReservation();
            $reservation->updatePaymentStatus();

            $entityManager->flush();

            $this->addFlash('success', $message);
        }

        return $this->redirectToRoute('app_payment_show', ['id' => $payment->getId()]);
    }

    #[Route('/pending', name: 'app_payment_pending', methods: ['GET'])]
    public function pending(PaymentRepository $paymentRepository): Response
    {
        $payments = $paymentRepository->findPendingPayments();

        return $this->render('payment_dashboard/pending.html.twig', [
            'payments' => $payments,
        ]);
    }

    private function refundPaymentAmount(Payment $payment, ?string $adminNotes, EntityManagerInterface $entityManager): string
    {
        $reservation = $payment->getReservation();
        $paymentAmount = (float) $payment->getAmount();
        $approvedTotal = $reservation ? $reservation->getTotalPaid() : $paymentAmount;
        $requiredTotal = $reservation ? max(0.0, (float) $reservation->getTotalAmount()) : 0.0;
        $excessAmount = max(0.0, $approvedTotal - $requiredTotal);

        if ($payment->getStatus() === Payment::STATUS_APPROVED && $excessAmount > 0) {
            $refundAmount = min($paymentAmount, $excessAmount);
            $keptAmount = $paymentAmount - $refundAmount;

            if ($keptAmount > 0) {
                $payment->setAmount(number_format($keptAmount, 2, '.', ''));
                $payment->setAdminNotes($this->appendNote($payment->getAdminNotes(), sprintf(
                    'Refunded excess amount of PHP %.2f.',
                    $refundAmount
                )));

                $refund = new Payment();
                $refund->setReservation($reservation);
                $refund->setPaidBy($payment->getPaidBy());
                $refund->setAmount(number_format($refundAmount, 2, '.', ''));
                $refund->setPaymentMethod($payment->getPaymentMethod() ?? Payment::METHOD_CASH);
                $refund->setReferenceNumber($payment->getReferenceNumber() ?: 'N/A');
                $refund->setGuestNotes($payment->getGuestNotes());
                $refund->setAdminNotes($adminNotes ?: 'Excess payment refunded.');
                $refund->setStatus(Payment::STATUS_REFUNDED);
                $refund->setApprovedBy($this->getUser());
                $refund->setApprovedAt(new \DateTime());
                $entityManager->persist($refund);
            } else {
                $payment->setStatus(Payment::STATUS_REFUNDED);
                $payment->setAdminNotes($this->appendNote($adminNotes, 'Full payment amount was excess and refunded.'));
            }

            return sprintf('Excess payment refunded: PHP %.2f.', $refundAmount);
        }

        $payment->setStatus(Payment::STATUS_REFUNDED);
        $payment->setAdminNotes($adminNotes);

        return 'Payment refunded.';
    }

    private function appendNote(?string $existing, ?string $note): ?string
    {
        if (!$note) return $existing;
        if (!$existing) return $note;

        return $existing . "\n" . $note;
    }
}
