<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Reservation;
use App\Repository\PaymentRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/payments')]
class PaymentDashboardController extends AbstractController
{
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
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('approve'.$payment->getId(), $request->request->get('_token'))) {
            $payment->setStatus(Payment::STATUS_APPROVED);
            $payment->setApprovedBy($this->getUser());
            $payment->setApprovedAt(new \DateTime());
            $payment->setAdminNotes($request->request->get('admin_notes'));

            // Update reservation payment status
            $reservation = $payment->getReservation();
            $reservation->updatePaymentStatus();

            // If fully paid, confirm reservation
            if ($reservation->getPaymentStatus() === Reservation::PAYMENT_PAID) {
                $reservation->setStatus(Reservation::STATUS_CONFIRMED);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Payment approved successfully!');
        }

        return $this->redirectToRoute('app_payment_show', ['id' => $payment->getId()]);
    }

    #[Route('/payment/{id}/reject', name: 'app_payment_reject', methods: ['POST'])]
    public function reject(
        Request $request,
        Payment $payment,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('reject'.$payment->getId(), $request->request->get('_token'))) {
            $payment->setStatus(Payment::STATUS_REJECTED);
            $payment->setApprovedBy($this->getUser());
            $payment->setApprovedAt(new \DateTime());
            $payment->setRejectionReason($request->request->get('rejection_reason'));
            $payment->setAdminNotes($request->request->get('admin_notes'));

            $entityManager->flush();

            $this->addFlash('success', 'Payment rejected.');
        }

        return $this->redirectToRoute('app_payment_show', ['id' => $payment->getId()]);
    }

    #[Route('/payment/{id}/refund', name: 'app_payment_refund', methods: ['POST'])]
    public function refund(
        Request $request,
        Payment $payment,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('refund'.$payment->getId(), $request->request->get('_token'))) {
            $payment->setStatus(Payment::STATUS_REFUNDED);
            $payment->setAdminNotes($request->request->get('admin_notes'));

            // Update reservation
            $reservation = $payment->getReservation();
            $reservation->updatePaymentStatus();

            $entityManager->flush();

            $this->addFlash('success', 'Payment refunded.');
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
}