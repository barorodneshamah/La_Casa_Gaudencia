<?php

namespace App\Controller;

use App\Repository\ContactMessageRepository;
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
    #[Route('/api/admin/notifications/pending', name: 'api_admin_notifications_pending', methods: ['GET'])]
    public function pending(
        Request $request,
        ReservationRepository $reservationRepo,
        PaymentRepository $paymentRepo,
        ContactMessageRepository $contactMessageRepo
    ): JsonResponse {
        $since = $request->query->get('since');
        $sinceDate = $since ? new \DateTime('@' . (int) $since) : new \DateTime('-1 hour');

        // New reservations from customers
        $newReservations = $reservationRepo->createQueryBuilder('r')
            ->select('r.id, r.reservationCode, r.serviceType, r.createdAt')
            ->addSelect('u.username, u.fullName')
            ->join('r.guest', 'u')
            ->where('r.createdAt > :since')
            ->setParameter('since', $sinceDate)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        // New pending payments from customers
        $newPayments = $paymentRepo->createQueryBuilder('p')
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

        // New contact messages from customers
        $newMessages = $contactMessageRepo->createQueryBuilder('m')
            ->select('m.id, m.fullName, m.subject, m.createdAt')
            ->where('m.createdAt > :since')
            ->setParameter('since', $sinceDate)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        $notifications = [];

        foreach ($newReservations as $r) {
            $createdAt = $this->dateValue($r['createdAt']);
            $notifications[] = [
                'type'    => 'reservation',
                'id'      => $r['id'],
                'message' => 'New reservation from ' . ($r['fullName'] ?: $r['username']),
                'detail'  => ucfirst(strtolower($r['serviceType'])) . ' — ' . ($r['reservationCode'] ?? '#' . $r['id']),
                'time'    => $createdAt->format('H:i'),
                'createdAt' => $createdAt->format(\DateTimeInterface::ATOM),
                'url'     => '/reservations/' . $r['id'],
            ];
        }

        foreach ($newPayments as $p) {
            $createdAt = $this->dateValue($p['createdAt']);
            $notifications[] = [
                'type'    => 'payment',
                'id'      => $p['id'],
                'message' => 'Payment ₱' . number_format((float)$p['amount'], 2) . ' from ' . ($p['fullName'] ?: $p['username']),
                'detail'  => $p['paymentMethod'] . ' — ' . $p['transactionReference'],
                'time'    => $createdAt->format('H:i'),
                'createdAt' => $createdAt->format(\DateTimeInterface::ATOM),
                'url'     => '/payments/payment/' . $p['id'],
            ];
        }

        foreach ($newMessages as $m) {
            $createdAt = $this->dateValue($m['createdAt']);
            $notifications[] = [
                'type'    => 'message',
                'id'      => $m['id'],
                'message' => 'New message from ' . ($m['fullName'] ?: 'Guest'),
                'detail'  => $m['subject'] ?: 'No subject',
                'time'    => $createdAt->format('H:i'),
                'createdAt' => $createdAt->format(\DateTimeInterface::ATOM),
                'url'     => '/dashboard/messages/' . $m['id'],
            ];
        }

        usort($notifications, fn($a, $b) => strcmp($b['createdAt'], $a['createdAt']));

        return $this->json([
            'count'         => count($notifications),
            'notifications' => $notifications,
            'checkedAt'     => time(),
        ]);
    }

    private function dateValue(mixed $value): \DateTimeInterface
    {
        return $value instanceof \DateTimeInterface ? $value : new \DateTime((string) $value);
    }
}
