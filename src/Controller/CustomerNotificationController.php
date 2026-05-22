<?php

namespace App\Controller;

use App\Repository\PaymentRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class CustomerNotificationController extends AbstractController
{
    #[Route('/customer/notifications/updates', name: 'customer_notifications_updates', methods: ['GET'])]
    public function updates(
        Request $request,
        ReservationRepository $reservationRepo,
        PaymentRepository $paymentRepo
    ): JsonResponse {
        $user = $this->getUser();
        $since = $request->query->get('since');
        $sinceDate = $since ? new \DateTime('@' . (int) $since) : new \DateTime('-5 minutes');

        $reservations = $reservationRepo->createQueryBuilder('r')
            ->where('r.guest = :user')
            ->andWhere('r.updatedAt > :since')
            ->andWhere('r.updatedAt > r.createdAt')
            ->setParameter('user', $user)
            ->setParameter('since', $sinceDate)
            ->orderBy('r.updatedAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $payments = $paymentRepo->createQueryBuilder('p')
            ->where('p.paidBy = :user')
            ->andWhere('p.updatedAt > :since')
            ->andWhere('p.updatedAt > p.createdAt')
            ->setParameter('user', $user)
            ->setParameter('since', $sinceDate)
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $notifications = [];

        foreach ($reservations as $r) {
            $statusLabels = [
                'PENDING'   => 'is pending review',
                'CONFIRMED' => 'has been confirmed',
                'CANCELLED' => 'has been cancelled',
                'COMPLETED' => 'has been completed',
            ];
            $status = $r->getStatus() ?? 'PENDING';
            $notifications[] = [
                'type'    => 'reservation',
                'id'      => $r->getId(),
                'message' => 'Reservation ' . $statusLabels[$status] ?? 'was updated',
                'detail'  => ucfirst($r->getServiceType() ?? '') . ' — ' . ($r->getReservationCode() ?? '#' . $r->getId()),
                'time'    => $r->getUpdatedAt()?->format('H:i'),
                'url'     => '/my-reservations/' . $r->getId(),
                'status'  => $status,
            ];
        }

        foreach ($payments as $p) {
            $statusLabels = [
                'PENDING'  => 'is pending review',
                'APPROVED' => 'has been approved',
                'REJECTED' => 'has been rejected',
                'VOID'     => 'has been voided',
            ];
            $status = $p->getStatus() ?? 'PENDING';
            $reservationId = $p->getReservation()?->getId();
            $notifications[] = [
                'type'    => 'payment',
                'id'      => $p->getId(),
                'message' => 'Payment ₱' . number_format((float)$p->getAmount(), 2) . ' ' . ($statusLabels[$status] ?? 'was updated'),
                'detail'  => $p->getPaymentMethod() . ' — ' . $p->getTransactionReference(),
                'time'    => $p->getUpdatedAt()?->format('H:i'),
                'url'     => $reservationId ? '/my-reservations/' . $reservationId : '/my-reservations',
                'status'  => $status,
            ];
        }

        usort($notifications, fn($a, $b) => strcmp($b['time'] ?? '', $a['time'] ?? ''));

        return $this->json([
            'count'         => count($notifications),
            'notifications' => $notifications,
            'checkedAt'     => time(),
        ]);
    }
}
