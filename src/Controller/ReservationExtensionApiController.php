<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/reservations')]
#[IsGranted('ROLE_USER')]
class ReservationExtensionApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReservationRepository $reservationRepo,
        private NotificationService $notifications
    ) {}

    /**
     * Guest requests an extension on their confirmed/completed reservation.
     *
     * For rooms:  POST { "newCheckoutDate": "YYYY-MM-DD" }
     * For tours:  POST { "newTourDate": "YYYY-MM-DD", "newParticipants": 2 }
     *
     * Returns: { extensionId, extensionCode, additionalCost, newDate, status }
     */
    #[Route('/{id}/request-extension', name: 'api_reservation_request_extension', methods: ['POST'])]
    public function requestExtension(int $id, Request $request): JsonResponse
    {
        $original = $this->reservationRepo->find($id);

        if (!$original) {
            return $this->json(['error' => 'Reservation not found'], 404);
        }

        if ($original->getGuest() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        if (!in_array($original->getStatus(), [Reservation::STATUS_CONFIRMED, Reservation::STATUS_COMPLETED], true)) {
            return $this->json(['error' => 'Only confirmed or completed reservations can be extended'], 400);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $serviceType = $original->getServiceType();

        $extension = new Reservation();
        $extension->setGuest($this->getUser());
        $extension->setServiceType($serviceType);
        $extension->setContactPhone($original->getContactPhone());
        $extension->setNumberOfGuests($original->getNumberOfGuests());
        $extension->setExtensionOf($original);

        $newDate = null;

        if ($serviceType === Reservation::SERVICE_ROOM && $original->getRoom()) {
            $rawDate = $data['newCheckoutDate'] ?? '';
            if (!$rawDate || !strtotime($rawDate)) {
                return $this->json(['error' => 'newCheckoutDate is required (YYYY-MM-DD)'], 400);
            }

            $newCheckout     = new \DateTime($rawDate);
            $currentCheckout = $original->getCheckOutDate();

            if ($newCheckout <= $currentCheckout) {
                return $this->json(['error' => 'New checkout date must be after the current checkout date (' . $currentCheckout->format('Y-m-d') . ')'], 400);
            }

            $extraNights = (int) $currentCheckout->diff($newCheckout)->days;

            $extension->setRoom($original->getRoom());
            // Extension period: from current checkout → new checkout
            $extension->setCheckInDate(clone $currentCheckout);
            $extension->setCheckOutDate($newCheckout);
            $extension->setSpecialRequests(
                'Extension request for ' . $original->getReservationCode() . ' (' . $extraNights . ' additional night' . ($extraNights !== 1 ? 's' : '') . ')'
            );

            $newDate = $newCheckout->format('Y-m-d');

        } elseif ($serviceType === Reservation::SERVICE_TOUR && $original->getTour()) {
            $rawDate = $data['newTourDate'] ?? '';
            if (!$rawDate || !strtotime($rawDate)) {
                return $this->json(['error' => 'newTourDate is required (YYYY-MM-DD)'], 400);
            }

            $newParticipants = isset($data['newParticipants']) ? (int) $data['newParticipants'] : ($original->getTourParticipants() ?? 1);

            $extension->setTour($original->getTour());
            $extension->setTourDate(new \DateTime($rawDate));
            $extension->setTourParticipants($newParticipants);
            $extension->setSpecialRequests(
                'Tour extension/rebook request for ' . $original->getReservationCode()
            );

            $newDate = (new \DateTime($rawDate))->format('Y-m-d');

        } elseif ($serviceType === Reservation::SERVICE_SPA && $original->getSpa()) {
            $rawDate = $data['newDate'] ?? '';
            if (!$rawDate || !strtotime($rawDate)) {
                return $this->json(['error' => 'newDate is required (YYYY-MM-DD)'], 400);
            }

            $extension->setSpa($original->getSpa());
            $extension->setTourDate(new \DateTime($rawDate)); // reuse tourDate field for spa date
            $extension->setSpecialRequests(
                'Spa rebooking request for ' . $original->getReservationCode()
            );

            $newDate = (new \DateTime($rawDate))->format('Y-m-d');

        } elseif ($serviceType === Reservation::SERVICE_PACKAGE && $original->getPackage()) {
            $extension->setPackage($original->getPackage());
            $extension->setCheckInDate(new \DateTime($data['newCheckInDate'] ?? 'now'));
            $extension->setCheckOutDate(new \DateTime($data['newCheckOutDate'] ?? '+3 days'));
            $extension->setSpecialRequests(
                'Package rebooking request for ' . $original->getReservationCode()
            );

            $newDate = $extension->getCheckInDate()->format('Y-m-d');

        } else {
            return $this->json(['error' => 'Extension not supported for this service type (' . $serviceType . ')'], 400);
        }

        $this->em->persist($extension);
        $this->em->flush();

        // Notify admin/staff via FCM if we have a way — skipped here since notifications flow admin→guest.
        // The admin will see the new PENDING extension in the reservation list.

        return $this->json([
            'extensionId'    => $extension->getId(),
            'extensionCode'  => $extension->getReservationCode(),
            'additionalCost' => $extension->getTotalAmount(),
            'newDate'        => $newDate,
            'status'         => $extension->getStatus(),
            'message'        => 'Extension request submitted successfully. Pending admin approval.',
        ]);
    }
}
