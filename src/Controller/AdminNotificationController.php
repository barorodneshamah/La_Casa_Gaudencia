<?php

namespace App\Controller;

use App\Repository\PaymentRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
class AdminNotificationController extends AbstractController
{
    #[Route('/admin/notifications/pending', name: 'admin_notifications_pending', methods: ['GET'])]
    public function pending(
        Request $request,
        ReservationRepository $reservationRepo,
        PaymentRepository $paymentRepo
    ): JsonResponse {
        // since = Unix timestamp from client (last check time)
        $since = $request->query->get('since');
        $sinceDate = $since ? new \DateTime('@' . (int) $since) : new \DateTime('-5 minutes');

        $pendingReservations = $reservationRepo->createQueryBuilder('r')
            ->select('r.id, r.reservationCode, r.serviceType, r.createdAt')
            ->addSelect('u.username, u.fullName')
            ->join('r.guest', 'u')
            ->where('r.createdAt > :since')
            ->setParameter('since', $sinceDate)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        $pendingPayments = $paymentRepo->createQueryBuilder('p')
            ->select('p.id, p.transactionReference, p.amount, p.paymentMethod, p.createdAt')
            ->addSelect('u.username, u.fullName')
            ->join('p.paidBy', 'u')
            ->where('p.status = :status')
            ->andWhere('p.createdAt > :since')
            ->setParameter('status', 'PENDING')
            ->setParameter('since', $sinceDate)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        $notifications = [];

        foreach ($pendingReservations as $r) {
            $notifications[] = [
                'type'    => 'reservation',
                'id'      => $r['id'],
                'message' => 'New reservation from ' . ($r['fullName'] ?: $r['username']),
                'detail'  => ucfirst($r['serviceType']) . ' — ' . ($r['reservationCode'] ?? '#' . $r['id']),
                'time'    => $r['createdAt'] instanceof \DateTimeInterface
                    ? $r['createdAt']->format('H:i')
                    : (new \DateTime($r['createdAt']))->format('H:i'),
                'url'     => '/reservations/' . $r['id'],
            ];
        }

        foreach ($pendingPayments as $p) {
            $notifications[] = [
                'type'    => 'payment',
                'id'      => $p['id'],
                'message' => 'Payment ₱' . number_format((float)$p['amount'], 2) . ' from ' . ($p['fullName'] ?: $p['username']),
                'detail'  => $p['paymentMethod'] . ' — ' . $p['transactionReference'],
                'time'    => $p['createdAt'] instanceof \DateTimeInterface
                    ? $p['createdAt']->format('H:i')
                    : (new \DateTime($p['createdAt']))->format('H:i'),
                'url'     => '/payments/payment/' . $p['id'],
            ];
        }

        usort($notifications, fn($a, $b) => strcmp($b['time'], $a['time']));

        return $this->json([
            'count'         => count($notifications),
            'notifications' => $notifications,
            'checkedAt'     => time(),
        ]);
    }
}
