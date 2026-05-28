<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Entity\ContactReply;
use App\Entity\Food;
use App\Entity\Package as TravelPackage;
use App\Entity\Payment;
use App\Entity\Reservation;
use App\Entity\Room;
use App\Entity\Spa;
use App\Entity\Tour;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use App\Service\WebSocketPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api')]
class MobileAdminApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationService $notifications,
        private WebSocketPublisher $ws,
        private UserRepository $users,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Route('/admin/users', name: 'api_mobile_admin_user_create', methods: ['POST'])]
    public function createAdminUser(Request $request): JsonResponse
    {
        if ($blocked = $this->adminOnly()) return $blocked;

        $data = $this->jsonBody($request);
        $password = trim((string) ($data['password'] ?? ''));
        if ($password === '') {
            return $this->json(['message' => 'Password is required.'], 422);
        }

        $user = new User();
        $this->applyUser($user, $data);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsVerified(true);

        $this->em->persist($user);
        $this->em->flush();

        return $this->json(['message' => 'User created.', 'user' => $this->userData($user)], 201);
    }

    #[Route('/admin/users/{id}', name: 'api_mobile_admin_user_update', methods: ['PUT', 'PATCH'])]
    public function updateAdminUser(User $user, Request $request): JsonResponse
    {
        if ($blocked = $this->adminOnly()) return $blocked;

        $data = $this->jsonBody($request);
        $this->applyUser($user, $data);

        $password = trim((string) ($data['password'] ?? ''));
        if ($password !== '') {
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        }

        $this->em->flush();

        return $this->json(['message' => 'User updated.', 'user' => $this->userData($user)]);
    }

    #[Route('/admin/users/{id}', name: 'api_mobile_admin_user_delete', methods: ['DELETE'])]
    public function deleteAdminUser(User $user): JsonResponse
    {
        if ($blocked = $this->adminOnly()) return $blocked;

        if ($user === $this->getUser()) {
            return $this->json(['message' => 'You cannot delete your own account from mobile.'], 422);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(['message' => 'User deleted.']);
    }

    #[Route('/payments/{id}/approve', name: 'api_mobile_payment_approve', methods: ['POST'])]
    public function approvePayment(Payment $payment, Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $data = $this->jsonBody($request);
        $payment->setStatus(Payment::STATUS_APPROVED);
        $payment->setApprovedBy($this->getUser());
        $payment->setApprovedAt(new \DateTime());
        $payment->setAdminNotes($data['adminNotes'] ?? $data['admin_notes'] ?? null);

        $reservation = $payment->getReservation();
        if ($reservation) {
            $reservation->updatePaymentStatus();
            if ($reservation->getPaymentStatus() === Reservation::PAYMENT_PAID) {
                $reservation->setStatus(Reservation::STATUS_CONFIRMED);
            }
        }

        $this->em->flush();
        $this->pushPayment($payment, 'Payment Approved', 'Your payment has been approved.', 'payment_approved');

        return $this->json(['message' => 'Payment approved successfully.', 'payment' => $this->paymentData($payment)]);
    }

    #[Route('/payments/{id}/reject', name: 'api_mobile_payment_reject', methods: ['POST'])]
    public function rejectPayment(Payment $payment, Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $data = $this->jsonBody($request);
        $reason = $data['reason'] ?? $data['rejectionReason'] ?? $data['rejection_reason'] ?? null;

        $payment->setStatus(Payment::STATUS_REJECTED);
        $payment->setApprovedBy($this->getUser());
        $payment->setApprovedAt(new \DateTime());
        $payment->setRejectionReason($reason);
        $payment->setAdminNotes($data['adminNotes'] ?? $data['admin_notes'] ?? $reason);

        $this->em->flush();
        $this->pushPayment($payment, 'Payment Rejected', 'Your payment was rejected. Please contact us for details.', 'payment_rejected');

        return $this->json(['message' => 'Payment rejected.', 'payment' => $this->paymentData($payment)]);
    }

    #[Route('/payments/{id}/refund', name: 'api_mobile_payment_refund', methods: ['POST'])]
    public function refundPayment(Payment $payment, Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $data = $this->jsonBody($request);
        $result = $this->refundPaymentAmount($payment, $data['adminNotes'] ?? $data['admin_notes'] ?? null);

        if ($payment->getReservation()) {
            $payment->getReservation()->updatePaymentStatus();
        }

        $this->em->flush();
        $this->pushPayment($payment, 'Payment Refunded', $result['message'], 'payment_refunded');

        return $this->json([
            'message' => $result['message'],
            'payment' => $this->paymentData($payment),
            'refundedAmount' => $result['refundedAmount'],
            'keptAmount' => $result['keptAmount'],
        ]);
    }

    #[Route('/reservations/{id}/approve', name: 'api_mobile_reservation_approve', methods: ['POST'])]
    public function approveReservation(Reservation $reservation, Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $data = $this->jsonBody($request);
        $reservation->setStatus(Reservation::STATUS_CONFIRMED);
        $reservation->setApprovedBy($this->getUser());
        $reservation->setApprovedAt(new \DateTime());
        $reservation->setAdminNotes($data['notes'] ?? $data['adminNotes'] ?? null);

        if ($reservation->isExtension()) {
            $this->applyExtensionToOriginal($reservation);
        }

        $this->em->flush();
        $this->pushReservation($reservation, 'Booking Confirmed', 'Your reservation has been confirmed.');

        return $this->json(['message' => 'Reservation approved successfully.', 'reservation' => $this->reservationData($reservation)]);
    }

    #[Route('/reservations/{id}/reject', name: 'api_mobile_reservation_reject', methods: ['POST'])]
    public function rejectReservation(Reservation $reservation, Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $data = $this->jsonBody($request);
        $reason = $data['notes'] ?? $data['reason'] ?? 'Reservation was rejected.';
        $reservation->setStatus(Reservation::STATUS_CANCELLED);
        $reservation->setAdminNotes($reason);

        $this->em->flush();
        $this->pushReservation($reservation, 'Booking Cancelled', 'Your reservation could not be confirmed. Please contact us for details.');

        return $this->json(['message' => 'Reservation rejected.', 'reservation' => $this->reservationData($reservation)]);
    }

    #[Route('/reservations/{id}/complete', name: 'api_mobile_reservation_complete', methods: ['POST'])]
    public function completeReservation(Reservation $reservation): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $reservation->setStatus(Reservation::STATUS_COMPLETED);
        $this->em->flush();
        $this->pushReservation($reservation, 'Booking Completed', 'Your reservation has been marked as completed.');

        return $this->json(['message' => 'Reservation marked as completed.', 'reservation' => $this->reservationData($reservation)]);
    }

    #[Route('/reservations/{id}/mark-paid', name: 'api_mobile_reservation_mark_paid', methods: ['POST'])]
    public function markReservationPaid(Reservation $reservation, Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $data = $this->jsonBody($request);
        $amount = $data['amount'] ?? $reservation->getRemainingBalance();

        $payment = new Payment();
        $payment->setReservation($reservation);
        $payment->setPaidBy($reservation->getGuest());
        $payment->setAmount((string) $amount);
        $payment->setPaymentMethod($data['paymentMethod'] ?? $data['payment_method'] ?? Payment::METHOD_CASH);
        $payment->setReferenceNumber($data['referenceNumber'] ?? $data['reference_number'] ?? 'N/A');
        $payment->setAdminNotes($data['adminNotes'] ?? $data['admin_notes'] ?? null);
        $payment->setStatus(Payment::STATUS_APPROVED);
        $payment->setApprovedBy($this->getUser());
        $payment->setApprovedAt(new \DateTime());

        $this->em->persist($payment);
        $reservation->updatePaymentStatus();
        if ($reservation->getPaymentStatus() === Reservation::PAYMENT_PAID && $reservation->isPending()) {
            $reservation->setStatus(Reservation::STATUS_CONFIRMED);
        }

        $this->em->flush();
        $this->pushPayment($payment, 'Payment Recorded', 'A payment was recorded for your reservation.', 'payment_recorded');

        return $this->json([
            'message' => 'Payment recorded successfully.',
            'reservation' => $this->reservationData($reservation),
            'payment' => $this->paymentData($payment),
        ]);
    }

    #[Route('/messages/{id}/status', name: 'api_mobile_message_status', methods: ['POST'])]
    public function updateMessageStatus(ContactMessage $message, Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $status = $this->jsonBody($request)['status'] ?? null;
        if (!in_array($status, ['unread', 'read', 'replied', 'archived'], true)) {
            return $this->json(['message' => 'Invalid message status.'], 422);
        }

        $message->setStatus($status);
        if (in_array($status, ['read', 'replied'], true)) {
            $message->setReadAt(new \DateTimeImmutable());
        }

        $this->em->flush();

        return $this->json(['message' => 'Message status updated.', 'contactMessage' => $this->messageData($message)]);
    }

    #[Route('/messages/{id}/reply', name: 'api_mobile_message_reply', methods: ['POST'])]
    public function replyToMessage(ContactMessage $message, Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $replyText = trim((string) ($this->jsonBody($request)['message'] ?? ''));
        if ($replyText === '') {
            return $this->json(['message' => 'Reply message is required.'], 422);
        }

        $reply = new ContactReply();
        $reply->setContactMessage($message);
        $reply->setRepliedBy($this->getUser());
        $reply->setReplyMessage($replyText);
        $reply->setCreatedAt(new \DateTimeImmutable());

        $message->setStatus('replied');
        $message->setReadAt(new \DateTimeImmutable());
        $message->addReply($reply);

        $this->em->persist($reply);
        $this->em->flush();
        $this->notifyMessageOwner($message);

        return $this->json(['message' => 'Reply sent.', 'contactMessage' => $this->messageData($message)]);
    }

    #[Route('/messages/{id}/delete', name: 'api_mobile_message_delete', methods: ['POST'])]
    public function deleteMessage(ContactMessage $message): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $this->em->remove($message);
        $this->em->flush();

        return $this->json(['message' => 'Message deleted.']);
    }

    #[Route('/foods/{id}', name: 'api_mobile_food_show_alias', methods: ['GET'])]
    public function showFood(Food $food): JsonResponse
    {
        return $this->json($this->foodData($food));
    }

    #[Route('/services/rooms', name: 'api_mobile_room_create', methods: ['POST'])]
    public function createRoom(Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $room = new Room();
        $this->applyRoom($room, $this->jsonBody($request));
        $this->em->persist($room);
        $this->em->flush();

        return $this->json(['message' => 'Room created.', 'room' => $this->roomData($room)], 201);
    }

    #[Route('/rooms/{id}', name: 'api_mobile_room_update', methods: ['PUT', 'PATCH'])]
    public function updateRoom(Room $room, Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $this->applyRoom($room, $this->jsonBody($request));
        $this->em->flush();

        return $this->json(['message' => 'Room updated.', 'room' => $this->roomData($room)]);
    }

    #[Route('/rooms/{id}', name: 'api_mobile_room_delete', methods: ['DELETE'])]
    public function deleteRoom(Room $room): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $this->em->remove($room);
        $this->em->flush();

        return $this->json(['message' => 'Room deleted.']);
    }

    #[Route('/services/tours', name: 'api_mobile_tour_create', methods: ['POST'])]
    public function createTour(Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $tour = new Tour();
        $this->applyTour($tour, $this->jsonBody($request));
        $this->em->persist($tour);
        $this->em->flush();

        return $this->json(['message' => 'Tour created.', 'tour' => $this->tourData($tour)], 201);
    }

    #[Route('/tours/{id}', name: 'api_mobile_tour_update', methods: ['PUT', 'PATCH'])]
    public function updateTour(Tour $tour, Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $this->applyTour($tour, $this->jsonBody($request));
        $this->em->flush();

        return $this->json(['message' => 'Tour updated.', 'tour' => $this->tourData($tour)]);
    }

    #[Route('/tours/{id}', name: 'api_mobile_tour_delete', methods: ['DELETE'])]
    public function deleteTour(Tour $tour): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $this->em->remove($tour);
        $this->em->flush();

        return $this->json(['message' => 'Tour deleted.']);
    }

    #[Route('/services/food', name: 'api_mobile_food_create', methods: ['POST'])]
    public function createFood(Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $food = new Food();
        $this->applyFood($food, $this->jsonBody($request));
        $this->em->persist($food);
        $this->em->flush();

        return $this->json(['message' => 'Food created.', 'food' => $this->foodData($food)], 201);
    }

    #[Route('/foods/{id}', name: 'api_mobile_food_update', methods: ['PUT', 'PATCH'])]
    public function updateFood(Food $food, Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $this->applyFood($food, $this->jsonBody($request));
        $this->em->flush();

        return $this->json(['message' => 'Food updated.', 'food' => $this->foodData($food)]);
    }

    #[Route('/foods/{id}', name: 'api_mobile_food_delete', methods: ['DELETE'])]
    public function deleteFood(Food $food): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $this->em->remove($food);
        $this->em->flush();

        return $this->json(['message' => 'Food deleted.']);
    }

    #[Route('/services/packages', name: 'api_mobile_package_create', methods: ['POST'])]
    public function createPackage(Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $package = new TravelPackage();
        $this->applyPackage($package, $this->jsonBody($request));
        $this->em->persist($package);
        $this->em->flush();

        return $this->json(['message' => 'Package created.', 'package' => $this->packageData($package)], 201);
    }

    #[Route('/services/packages/{id}', name: 'api_mobile_package_update', methods: ['PUT', 'PATCH'])]
    public function updatePackage(TravelPackage $package, Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $this->applyPackage($package, $this->jsonBody($request));
        $this->em->flush();

        return $this->json(['message' => 'Package updated.', 'package' => $this->packageData($package)]);
    }

    #[Route('/services/packages/{id}', name: 'api_mobile_package_delete', methods: ['DELETE'])]
    public function deletePackage(TravelPackage $package): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $this->em->remove($package);
        $this->em->flush();

        return $this->json(['message' => 'Package deleted.']);
    }

    #[Route('/services/spas', name: 'api_mobile_spa_create', methods: ['POST'])]
    public function createSpa(Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $spa = new Spa();
        $this->applySpa($spa, $this->jsonBody($request));
        $this->em->persist($spa);
        $this->em->flush();

        return $this->json(['message' => 'Spa service created.', 'spa' => $this->spaData($spa)], 201);
    }

    #[Route('/spas/{id}', name: 'api_mobile_spa_update', methods: ['PUT', 'PATCH'])]
    public function updateSpa(Spa $spa, Request $request): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $this->applySpa($spa, $this->jsonBody($request));
        $this->em->flush();

        return $this->json(['message' => 'Spa service updated.', 'spa' => $this->spaData($spa)]);
    }

    #[Route('/spas/{id}', name: 'api_mobile_spa_delete', methods: ['DELETE'])]
    public function deleteSpa(Spa $spa): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $this->em->remove($spa);
        $this->em->flush();

        return $this->json(['message' => 'Spa service deleted.']);
    }

    #[Route('/services/upload-image', name: 'api_mobile_service_upload_image', methods: ['POST'])]
    public function uploadServiceImage(Request $request, SluggerInterface $slugger): JsonResponse
    {
        if ($blocked = $this->staffOnly()) return $blocked;

        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file uploaded.'], 422);
        }

        $serviceType = (string) $request->request->get('serviceType', '');
        $folders = [
            'rooms' => 'rooms',
            'tours' => 'tours',
            'foods' => 'foods',
            'packages' => 'packages',
            'spas' => 'spas',
        ];
        if (!isset($folders[$serviceType])) {
            return $this->json(['error' => 'Invalid service type.'], 422);
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return $this->json(['error' => 'Please upload a valid image.'], 422);
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $slugger->slug($originalName ?: 'service-image')->lower();
        $extension = $file->guessExtension() ?: 'jpg';
        $fileName = sprintf('%s_%s_%s.%s', rtrim($serviceType, 's'), $safeName, uniqid(), $extension);
        $relativeDir = 'uploads/' . $folders[$serviceType];
        $targetDir = $this->getParameter('kernel.project_dir') . '/public/' . $relativeDir;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $file->move($targetDir, $fileName);

        return $this->json(['url' => $relativeDir . '/' . $fileName]);
    }

    private function staffOnly(): ?JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            return $this->json(['message' => 'Only admin and staff can perform this action.'], 403);
        }

        return null;
    }

    private function adminOnly(): ?JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => 'Only admins can perform this action.'], 403);
        }

        return null;
    }

    private function jsonBody(Request $request): array
    {
        $data = json_decode($request->getContent() ?: '{}', true);
        return is_array($data) ? $data : [];
    }

    private function applyUser(User $user, array $data): void
    {
        if (array_key_exists('username', $data)) $user->setUsername(trim((string) $data['username']));
        if (array_key_exists('email', $data)) $user->setEmail(trim((string) $data['email']));
        if (array_key_exists('fullName', $data)) $user->setFullName(trim((string) $data['fullName']));
        if (array_key_exists('roles', $data)) $user->setRoles($this->sanitizeUserRoles($data['roles']));
    }

    private function sanitizeUserRoles(mixed $roles): array
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $allowed = [User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_GUEST];
        $clean = array_values(array_intersect($allowed, array_map('strval', $roles)));

        return $clean ?: [User::ROLE_GUEST];
    }

    private function applyRoom(Room $room, array $data): void
    {
        if (array_key_exists('roomNumber', $data)) $room->setRoomNumber((string) $data['roomNumber']);
        if (array_key_exists('roomType', $data)) $room->setRoomType((string) $data['roomType']);
        if (array_key_exists('pricePerNight', $data)) $room->setPricePerNight((string) $data['pricePerNight']);
        if (array_key_exists('capacity', $data)) $room->setCapacity((int) $data['capacity']);
        if (array_key_exists('features', $data)) $room->setFeatures($this->toText($data['features']));
        if (array_key_exists('description', $data)) $room->setDescription((string) $data['description']);
        if (array_key_exists('status', $data)) $room->setStatus((string) $data['status']);
        if (array_key_exists('mainImage', $data)) $room->setMainImage($data['mainImage']);
        if (array_key_exists('galleryImages', $data)) $room->setGalleryImages($data['galleryImages']);
        if (array_key_exists('isOffer', $data)) $room->setIsOffer((bool) $data['isOffer']);
    }

    private function applyTour(Tour $tour, array $data): void
    {
        if (array_key_exists('name', $data)) $tour->setName((string) $data['name']);
        if (array_key_exists('description', $data)) $tour->setDescription((string) $data['description']);
        if (array_key_exists('location', $data)) $tour->setLocation((string) $data['location']);
        if (array_key_exists('price', $data)) $tour->setPrice((string) $data['price']);
        if (array_key_exists('duration', $data)) $tour->setDuration((string) $data['duration']);
        if (array_key_exists('availableSlots', $data)) $tour->setAvailableSlots((int) $data['availableSlots']);
        if (array_key_exists('status', $data)) $tour->setStatus((string) $data['status']);
        if (array_key_exists('scheduleDate', $data)) $tour->setScheduleDate($this->dateOrNull($data['scheduleDate']));
        if (array_key_exists('mainImage', $data)) $tour->setMainImage($data['mainImage']);
        if (array_key_exists('galleryImages', $data)) $tour->setGalleryImages($data['galleryImages']);
        if (array_key_exists('isOffer', $data)) $tour->setIsOffer((bool) $data['isOffer']);
    }

    private function applyFood(Food $food, array $data): void
    {
        if (array_key_exists('name', $data)) $food->setName((string) $data['name']);
        if (array_key_exists('description', $data)) $food->setDescription((string) $data['description']);
        if (array_key_exists('price', $data)) $food->setPrice((string) $data['price']);
        if (array_key_exists('category', $data)) $food->setCategory((string) $data['category']);
        if (array_key_exists('availableStock', $data)) $food->setAvailableStock((int) $data['availableStock']);
        if (array_key_exists('status', $data)) $food->setStatus((string) $data['status']);
        if (array_key_exists('mainImage', $data)) $food->setMainImage($data['mainImage']);
        if (array_key_exists('galleryImages', $data)) $food->setGalleryImages($data['galleryImages']);
        if (array_key_exists('isOffer', $data)) $food->setIsOffer((bool) $data['isOffer']);
    }

    private function applyPackage(TravelPackage $package, array $data): void
    {
        $autoCalculatePrices = (bool) ($data['autoCalculatePrices'] ?? false);
        $requestedDiscount = array_key_exists('discountPercentage', $data)
            ? max(0.0, min(100.0, (float) $data['discountPercentage']))
            : null;

        if (array_key_exists('name', $data)) $package->setName((string) $data['name']);
        if (array_key_exists('description', $data)) $package->setDescription((string) $data['description']);
        if (array_key_exists('originalPrice', $data)) $package->setOriginalPrice((string) $data['originalPrice']);
        if (array_key_exists('packagePrice', $data)) $package->setPackagePrice((string) $data['packagePrice']);
        if (array_key_exists('discountPercentage', $data)) $package->setDiscountPercentage((string) $data['discountPercentage']);
        if (array_key_exists('validFrom', $data)) $package->setValidFrom($this->dateOrNull($data['validFrom']));
        if (array_key_exists('validUntil', $data)) $package->setValidUntil($this->dateOrNull($data['validUntil']));
        if (array_key_exists('maxRedemptions', $data)) $package->setMaxRedemptions((int) $data['maxRedemptions']);
        if (array_key_exists('status', $data)) $package->setStatus((string) $data['status']);
        if (array_key_exists('packageType', $data)) $package->setPackageType((string) $data['packageType']);
        if (array_key_exists('mainImage', $data)) $package->setMainImage($data['mainImage']);
        if (array_key_exists('galleryImages', $data)) $package->setGalleryImages($data['galleryImages']);
        if (array_key_exists('termsAndConditions', $data)) $package->setTermsAndConditions((string) $data['termsAndConditions']);
        if (array_key_exists('inclusions', $data)) $package->setInclusions($this->toText($data['inclusions']));
        if (array_key_exists('exclusions', $data)) $package->setExclusions($this->toText($data['exclusions']));
        if (array_key_exists('durationDays', $data)) $package->setDurationDays((int) $data['durationDays']);
        if (array_key_exists('durationNights', $data)) $package->setDurationNights((int) $data['durationNights']);
        if (array_key_exists('maxGuests', $data)) $package->setMaxGuests((int) $data['maxGuests']);
        if (array_key_exists('isFeatured', $data)) $package->setIsFeatured((bool) $data['isFeatured']);
        if (array_key_exists('isOffer', $data)) $package->setIsOffer((bool) $data['isOffer']);
        if (array_key_exists('roomIds', $data) || array_key_exists('rooms', $data)) {
            foreach ($package->getRooms()->toArray() as $room) {
                $package->removeRoom($room);
            }
            foreach ($this->idsFrom($data['roomIds'] ?? $data['rooms']) as $id) {
                $room = $this->em->getRepository(Room::class)->find($id);
                if ($room) $package->addRoom($room);
            }
        }
        if (array_key_exists('tourIds', $data) || array_key_exists('tours', $data)) {
            foreach ($package->getTours()->toArray() as $tour) {
                $package->removeTour($tour);
            }
            foreach ($this->idsFrom($data['tourIds'] ?? $data['tours']) as $id) {
                $tour = $this->em->getRepository(Tour::class)->find($id);
                if ($tour) $package->addTour($tour);
            }
        }
        if (array_key_exists('foodIds', $data) || array_key_exists('foods', $data)) {
            foreach ($package->getFoods()->toArray() as $food) {
                $package->removeFood($food);
            }
            foreach ($this->idsFrom($data['foodIds'] ?? $data['foods']) as $id) {
                $food = $this->em->getRepository(Food::class)->find($id);
                if ($food) $package->addFood($food);
            }
        }

        $package->calculateOriginalPrice();
        if ($autoCalculatePrices) {
            $originalPrice = (float) ($package->getOriginalPrice() ?? 0);
            if ($originalPrice > 0 && $requestedDiscount !== null) {
                $packagePrice = $originalPrice - ($originalPrice * $requestedDiscount / 100);
                $package->setPackagePrice(number_format(max(0, $packagePrice), 2, '.', ''));
            } elseif ($originalPrice > 0 && (float) ($package->getPackagePrice() ?? 0) <= 0) {
                $package->setPackagePrice(number_format($originalPrice, 2, '.', ''));
            }
        }
        $package->calculateDiscount();
    }

    private function idsFrom(mixed $value): array
    {
        if ($value === null || $value === '') return [];
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(array_map(
            function (mixed $item): int {
                $raw = is_array($item) ? ($item['id'] ?? 0) : $item;
                if (is_string($raw) && preg_match('/(\d+)$/', $raw, $matches)) {
                    return (int) $matches[1];
                }
                return (int) $raw;
            },
            $values
        ))));
    }

    private function applySpa(Spa $spa, array $data): void
    {
        if (array_key_exists('name', $data)) $spa->setName((string) $data['name']);
        if (array_key_exists('description', $data)) $spa->setDescription((string) $data['description']);
        if (array_key_exists('price', $data)) $spa->setPrice((string) $data['price']);
        if (array_key_exists('duration', $data)) $spa->setDuration((string) $data['duration']);
        if (array_key_exists('capacity', $data)) $spa->setCapacity((int) $data['capacity']);
        if (array_key_exists('availableSlots', $data)) $spa->setCapacity((int) $data['availableSlots']);
        if (array_key_exists('category', $data)) $spa->setCategory((string) $data['category']);
        if (array_key_exists('status', $data)) $spa->setStatus((string) $data['status']);
        if (array_key_exists('mainImage', $data)) $spa->setMainImage($data['mainImage']);
        if (array_key_exists('galleryImages', $data)) $spa->setGalleryImages($data['galleryImages']);
        if (array_key_exists('isOffer', $data)) $spa->setIsOffer((bool) $data['isOffer']);
    }

    private function applyExtensionToOriginal(Reservation $reservation): void
    {
        $original = $reservation->getExtensionOf();
        if (!$original) return;

        if ($reservation->getServiceType() === Reservation::SERVICE_ROOM && $reservation->getCheckOutDate()) {
            $original->setCheckOutDate($reservation->getCheckOutDate());
        } elseif ($reservation->getTourDate()) {
            $original->setTourDate($reservation->getTourDate());
            if ($reservation->getTourParticipants()) {
                $original->setTourParticipants($reservation->getTourParticipants());
            }
        }
    }

    private function pushPayment(Payment $payment, string $title, string $body, string $type): void
    {
        $guest = $payment->getPaidBy();
        if (!$guest) return;

        $data = [
            'type' => 'payment',
            'event' => $type,
            'paymentId' => (string) $payment->getId(),
            'transactionRef' => (string) $payment->getTransactionReference(),
        ];

        try {
            $this->ws->pushToUser($guest->getUsername(), $title, $body, 'payment', $data);
        } catch (\Throwable) {}

        try {
            if ($guest->getFcmToken()) {
                $this->notifications->sendToDevice($guest->getFcmToken(), $title, $body, $data, 'payment_' . $payment->getId());
            }
        } catch (\Throwable) {}
    }

    private function refundPaymentAmount(Payment $payment, ?string $adminNotes = null): array
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
                $this->em->persist($refund);
            } else {
                $payment->setStatus(Payment::STATUS_REFUNDED);
                $payment->setAdminNotes($this->appendNote($adminNotes, 'Full payment amount was excess and refunded.'));
            }

            if ($reservation) {
                $reservation->updatePaymentStatus();
            }

            return [
                'message' => sprintf('Excess payment refunded: PHP %.2f.', $refundAmount),
                'refundedAmount' => number_format($refundAmount, 2, '.', ''),
                'keptAmount' => number_format(max(0.0, $keptAmount), 2, '.', ''),
            ];
        }

        $payment->setStatus(Payment::STATUS_REFUNDED);
        $payment->setAdminNotes($adminNotes);

        if ($reservation) {
            $reservation->updatePaymentStatus();
        }

        return [
            'message' => 'Payment refunded.',
            'refundedAmount' => number_format($paymentAmount, 2, '.', ''),
            'keptAmount' => '0.00',
        ];
    }

    private function appendNote(?string $existing, ?string $note): ?string
    {
        if (!$note) return $existing;
        if (!$existing) return $note;

        return $existing . "\n" . $note;
    }

    private function pushReservation(Reservation $reservation, string $title, string $body): void
    {
        $guest = $reservation->getGuest();
        if (!$guest) return;

        $data = [
            'type' => 'reservation',
            'reservationId' => (string) $reservation->getId(),
        ];

        try {
            $this->ws->pushToUser($guest->getUsername(), $title, $body, 'reservation', $data);
        } catch (\Throwable) {}

        try {
            if ($guest->getFcmToken()) {
                $this->notifications->sendToDevice($guest->getFcmToken(), $title, $body, $data);
            }
        } catch (\Throwable) {}
    }

    private function notifyMessageOwner(ContactMessage $message): void
    {
        $user = $this->users->findOneBy(['email' => $message->getEmail()]);
        if (!$user) return;

        $title = 'Reply to your message';
        $body = 'Re: ' . $message->getSubject();
        $data = [
            'type' => 'message',
            'messageId' => (string) $message->getId(),
        ];

        try {
            $this->ws->pushToUser($user->getUsername(), $title, $body, 'message', $data);
        } catch (\Throwable) {}

        try {
            if ($user->getFcmToken()) {
                $this->notifications->sendToDevice($user->getFcmToken(), $title, $body, $data, 'contact_reply_' . $message->getId());
            }
        } catch (\Throwable) {}
    }

    private function dateOrNull(mixed $value): ?\DateTime
    {
        if (!$value) return null;
        return new \DateTime((string) $value);
    }

    private function toText(mixed $value): ?string
    {
        if ($value === null) return null;
        if (is_array($value)) {
            return implode(', ', array_filter(array_map('strval', $value)));
        }

        return (string) $value;
    }

    private function paymentData(Payment $payment): array
    {
        return [
            'id' => $payment->getId(),
            'status' => $payment->getStatus(),
            'amount' => $payment->getAmount(),
            'paymentMethod' => $payment->getPaymentMethod(),
            'transactionReference' => $payment->getTransactionReference(),
            'reservation' => $payment->getReservation()?->getId(),
        ];
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $user->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function reservationData(Reservation $reservation): array
    {
        return [
            'id' => $reservation->getId(),
            'reservationCode' => $reservation->getReservationCode(),
            'status' => $reservation->getStatus(),
            'paymentStatus' => $reservation->getPaymentStatus(),
            'serviceType' => $reservation->getServiceType(),
            'adminNotes' => $reservation->getAdminNotes(),
        ];
    }

    private function messageData(ContactMessage $message): array
    {
        return [
            'id' => $message->getId(),
            'fullName' => $message->getFullName(),
            'email' => $message->getEmail(),
            'subject' => $message->getSubject(),
            'message' => $message->getMessage(),
            'status' => $message->getStatus(),
            'readAt' => $message->getReadAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $message->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'replies' => array_map(fn (ContactReply $reply) => $this->replyData($reply), $message->getReplies()->toArray()),
        ];
    }

    private function replyData(ContactReply $reply): array
    {
        $author = $reply->getRepliedBy();

        return [
            'id' => $reply->getId(),
            'replyMessage' => $reply->getReplyMessage(),
            'createdAt' => $reply->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'repliedBy' => $author ? [
                'id' => $author->getId(),
                'fullName' => $author->getFullName(),
                'email' => $author->getEmail(),
            ] : null,
        ];
    }

    private function roomData(Room $room): array
    {
        return [
            'id' => $room->getId(),
            'roomNumber' => $room->getRoomNumber(),
            'roomType' => $room->getRoomType(),
            'pricePerNight' => $room->getPricePerNight(),
            'capacity' => $room->getCapacity(),
            'features' => $room->getFeatures(),
            'description' => $room->getDescription(),
            'status' => $room->getStatus(),
            'mainImage' => $room->getMainImage(),
            'galleryImages' => $room->getGalleryImages(),
            'isOffer' => $room->isOffer(),
        ];
    }

    private function tourData(Tour $tour): array
    {
        return [
            'id' => $tour->getId(),
            'name' => $tour->getName(),
            'description' => $tour->getDescription(),
            'location' => $tour->getLocation(),
            'price' => $tour->getPrice(),
            'duration' => $tour->getDuration(),
            'availableSlots' => $tour->getAvailableSlots(),
            'status' => $tour->getStatus(),
            'scheduleDate' => $tour->getScheduleDate()?->format('Y-m-d'),
            'mainImage' => $tour->getMainImage(),
            'galleryImages' => $tour->getGalleryImages(),
            'isOffer' => $tour->isOffer(),
        ];
    }

    private function foodData(Food $food): array
    {
        return [
            'id' => $food->getId(),
            'name' => $food->getName(),
            'description' => $food->getDescription(),
            'price' => $food->getPrice(),
            'category' => $food->getCategory(),
            'availableStock' => $food->getAvailableStock(),
            'status' => $food->getStatus(),
            'mainImage' => $food->getMainImage(),
            'galleryImages' => $food->getGalleryImages(),
            'isOffer' => $food->isOffer(),
        ];
    }

    private function packageData(TravelPackage $package): array
    {
        return [
            'id' => $package->getId(),
            'name' => $package->getName(),
            'description' => $package->getDescription(),
            'originalPrice' => $package->getOriginalPrice(),
            'packagePrice' => $package->getPackagePrice(),
            'discountPercentage' => $package->getDiscountPercentage(),
            'validFrom' => $package->getValidFrom()?->format('Y-m-d'),
            'validUntil' => $package->getValidUntil()?->format('Y-m-d'),
            'status' => $package->getStatus(),
            'mainImage' => $package->getMainImage(),
            'galleryImages' => $package->getGalleryImages(),
            'inclusions' => $package->getInclusions(),
            'exclusions' => $package->getExclusions(),
            'durationDays' => $package->getDurationDays(),
            'durationNights' => $package->getDurationNights(),
            'maxGuests' => $package->getMaxGuests(),
            'isFeatured' => $package->isFeatured(),
            'isOffer' => $package->isOffer(),
            'roomIds' => array_map(fn (Room $room) => $room->getId(), $package->getRooms()->toArray()),
            'tourIds' => array_map(fn (Tour $tour) => $tour->getId(), $package->getTours()->toArray()),
            'foodIds' => array_map(fn (Food $food) => $food->getId(), $package->getFoods()->toArray()),
            'rooms' => array_map(fn (Room $room) => $this->roomData($room), $package->getRooms()->toArray()),
            'tours' => array_map(fn (Tour $tour) => $this->tourData($tour), $package->getTours()->toArray()),
            'foods' => array_map(fn (Food $food) => $this->foodData($food), $package->getFoods()->toArray()),
        ];
    }

    private function spaData(Spa $spa): array
    {
        return [
            'id' => $spa->getId(),
            'name' => $spa->getName(),
            'description' => $spa->getDescription(),
            'price' => $spa->getPrice(),
            'duration' => $spa->getDuration(),
            'capacity' => $spa->getCapacity(),
            'category' => $spa->getCategory(),
            'status' => $spa->getStatus(),
            'mainImage' => $spa->getMainImage(),
            'galleryImages' => $spa->getGalleryImages(),
            'isOffer' => $spa->isOffer(),
        ];
    }
}
