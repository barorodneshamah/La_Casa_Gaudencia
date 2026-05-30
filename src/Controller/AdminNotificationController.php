<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
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

    #[Route('/admin/live/reservations', name: 'admin_live_reservations', methods: ['GET'])]
    public function liveReservations(Request $request, ReservationRepository $reservationRepo): JsonResponse
    {
        $since = $request->query->get('since');
        $sinceDate = $since ? new \DateTime('@' . (int) $since) : new \DateTime('-10 seconds');

        $rows = $reservationRepo->createQueryBuilder('r')
            ->select('r.id, r.reservationCode, r.status, r.paymentStatus, r.totalAmount, r.serviceType, r.contactPhone, r.createdAt')
            ->addSelect('u.email AS guestEmail')
            ->addSelect('CASE WHEN rm.id IS NOT NULL THEN 1 ELSE 0 END AS hasRoom')
            ->addSelect('CASE WHEN t.id  IS NOT NULL THEN 1 ELSE 0 END AS hasTour')
            ->addSelect('CASE WHEN pk.id IS NOT NULL THEN 1 ELSE 0 END AS hasPackage')
            ->addSelect('CASE WHEN sp.id IS NOT NULL THEN 1 ELSE 0 END AS hasSpa')
            ->leftJoin('r.guest',   'u')
            ->leftJoin('r.room',    'rm')
            ->leftJoin('r.tour',    't')
            ->leftJoin('r.package', 'pk')
            ->leftJoin('r.spa',     'sp')
            ->where('r.createdAt > :since')
            ->setParameter('since', $sinceDate)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getArrayResult();

        return $this->json([
            'reservations' => array_map(fn($r) => [
                'id'             => $r['id'],
                'reservationCode'=> $r['reservationCode'],
                'status'         => $r['status'],
                'paymentStatus'  => $r['paymentStatus'],
                'totalAmount'    => $r['totalAmount'],
                'serviceType'    => $r['serviceType'],
                'contactPhone'   => $r['contactPhone'],
                'guestEmail'     => $r['guestEmail'],
                'createdAt'      => $this->dateValue($r['createdAt'])->format('M d, Y'),
                'hasRoom'        => (bool) $r['hasRoom'],
                'hasTour'        => (bool) $r['hasTour'],
                'hasPackage'     => (bool) $r['hasPackage'],
                'hasSpa'         => (bool) $r['hasSpa'],
                'hasFood'        => false,
            ], $rows),
            'checkedAt' => time(),
        ]);
    }

    #[Route('/admin/live/payments', name: 'admin_live_payments', methods: ['GET'])]
    public function livePayments(Request $request, PaymentRepository $paymentRepo): JsonResponse
    {
        $since = $request->query->get('since');
        $sinceDate = $since ? new \DateTime('@' . (int) $since) : new \DateTime('-10 seconds');

        $rows = $paymentRepo->createQueryBuilder('p')
            ->select('p.id, p.transactionReference, p.amount, p.paymentMethod, p.status, p.referenceNumber, p.guestNotes, p.createdAt')
            ->addSelect('u.email AS guestEmail, u.fullName AS guestName')
            ->addSelect('r.id AS reservationId, r.reservationCode, r.serviceType')
            ->leftJoin('p.paidBy',      'u')
            ->leftJoin('p.reservation', 'r')
            ->where('p.createdAt > :since')
            ->andWhere('p.status = :status')
            ->setParameter('since', $sinceDate)
            ->setParameter('status', 'PENDING')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getArrayResult();

        return $this->json([
            'payments' => array_map(fn($p) => [
                'id'              => $p['id'],
                'transactionRef'  => $p['transactionReference'],
                'amount'          => $p['amount'],
                'paymentMethod'   => $p['paymentMethod'],
                'status'          => $p['status'],
                'referenceNumber' => $p['referenceNumber'],
                'guestNotes'      => $p['guestNotes'],
                'guestEmail'      => $p['guestEmail'],
                'guestName'       => $p['guestName'],
                'reservationId'   => $p['reservationId'],
                'reservationCode' => $p['reservationCode'],
                'serviceType'     => $p['serviceType'],
                'createdAt'       => $this->dateValue($p['createdAt'])->format('M d, Y h:i A'),
                'hoursAgo'        => (int) round((time() - $this->dateValue($p['createdAt'])->getTimestamp()) / 3600),
            ], $rows),
            'checkedAt' => time(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/live/activity-logs', name: 'admin_live_activity_logs', methods: ['GET'])]
    public function liveActivityLogs(Request $request, ActivityLogRepository $activityLogRepo): JsonResponse
    {
        $since = $request->query->get('since');
        $sinceDate = $since ? new \DateTime('@' . (int) $since) : new \DateTime('-10 seconds');

        $logs = $activityLogRepo->createQueryBuilder('l')
            ->where('l.createdAt > :since')
            ->setParameter('since', $sinceDate)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $this->json([
            'logs' => array_map(fn($l) => [
                'id'          => $l->getId(),
                'createdAt'   => $l->getCreatedAt()->format('M d, Y'),
                'createdTime' => $l->getCreatedAt()->format('h:i:s A'),
                'username'    => $l->getUsername(),
                'userRole'    => $l->getUserRole(),
                'action'      => $l->getAction(),
                'entityType'  => $l->getEntityType(),
                'entityId'    => $l->getEntityId(),
                'description' => $l->getDescription(),
                'ipAddress'   => $l->getIpAddress(),
                'source'      => $l->getSource(),
            ], $logs),
            'checkedAt' => time(),
        ]);
    }

    private function dateValue(mixed $value): \DateTimeInterface
    {
        return $value instanceof \DateTimeInterface ? $value : new \DateTime((string) $value);
    }
}
