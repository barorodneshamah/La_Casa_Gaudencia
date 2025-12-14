<?php
// src/Controller/GuestReservationController.php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Repository\RoomRepository;
use App\Repository\TourRepository;
use App\Repository\PackageRepository;
use App\Repository\FoodRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/my-reservations')]
#[IsGranted('ROLE_USER')]
class GuestReservationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReservationRepository $reservationRepo,
        private RoomRepository $roomRepo,
        private TourRepository $tourRepo,
        private PackageRepository $packageRepo,
        private FoodRepository $foodRepo
    ) {}

    /**
     * List guest's reservations
     */
    #[Route('', name: 'guest_reservation_index', methods: ['GET'])]
    public function index(): Response
    {
        $reservations = $this->reservationRepo->findByGuest($this->getUser());

        return $this->render('guest/reservation/index.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    /**
     * Show booking form and handle submission
     */
    #[Route('/book', name: 'guest_reservation_book', methods: ['GET', 'POST'])]
    public function book(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $reservation = $this->createReservationFromRequest($request);
            
            $this->em->persist($reservation);
            $this->em->flush();

            $this->addFlash('success', 'Reservation submitted! Code: ' . $reservation->getReservationCode());
            return $this->redirectToRoute('guest_reservation_index');
        }

        return $this->render('guest/reservation/book.html.twig', [
            'rooms' => $this->roomRepo->findBy(['status' => 'Available']),
            'tours' => $this->tourRepo->findBy(['status' => 'Available']),
            'packages' => $this->packageRepo->findBy(['status' => 'Active']),
            'foods' => $this->foodRepo->findBy(['status' => 'Available']),
        ]);
    }

    /**
     * View single reservation
     */
    #[Route('/{id}', name: 'guest_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        $this->checkOwnership($reservation);

        $foodDetails = $this->getFoodDetails($reservation);

        return $this->render('guest/reservation/show.html.twig', [
            'reservation' => $reservation,
            'foodDetails' => $foodDetails,
        ]);
    }

    /**
     * Cancel pending reservation
     */
    #[Route('/{id}/cancel', name: 'guest_reservation_cancel', methods: ['POST'])]
    public function cancel(Request $request, Reservation $reservation): Response
    {
        $this->checkOwnership($reservation);

        if ($this->isCsrfTokenValid('cancel' . $reservation->getId(), $request->request->get('_token'))) {
            if ($reservation->isPending()) {
                $reservation->setStatus(Reservation::STATUS_CANCELLED);
                $this->em->flush();
                $this->addFlash('success', 'Reservation cancelled successfully.');
            } else {
                $this->addFlash('error', 'Only pending reservations can be cancelled.');
            }
        }

        return $this->redirectToRoute('guest_reservation_index');
    }

    /**
     * Create reservation from form request
     */
    private function createReservationFromRequest(Request $request): Reservation
    {
        $reservation = new Reservation();
        $reservation->setGuest($this->getUser());
        $reservation->setContactPhone($request->request->get('phone'));
        $reservation->setNumberOfGuests($request->request->getInt('guests', 1));
        $reservation->setSpecialRequests($request->request->get('requests'));

        $total = 0.00;

        // Room
        if ($roomId = $request->request->get('room_id')) {
            $room = $this->roomRepo->find($roomId);
            if ($room) {
                $reservation->setRoom($room);
                $checkIn = new \DateTime($request->request->get('check_in'));
                $checkOut = new \DateTime($request->request->get('check_out'));
                $reservation->setCheckInDate($checkIn);
                $reservation->setCheckOutDate($checkOut);
                
                $nights = max(1, $checkIn->diff($checkOut)->days);
                $total += (float)$room->getPricePerNight() * $nights;
            }
        }

        // Tour
        if ($tourId = $request->request->get('tour_id')) {
            $tour = $this->tourRepo->find($tourId);
            if ($tour) {
                $reservation->setTour($tour);
                $participants = $request->request->getInt('participants', 1);
                $reservation->setTourParticipants($participants);
                $reservation->setTourDate(new \DateTime($request->request->get('tour_date')));
                
                $total += (float)$tour->getPrice() * $participants;
            }
        }

        // Package
        if ($packageId = $request->request->get('package_id')) {
            $package = $this->packageRepo->find($packageId);
            if ($package) {
                $reservation->setPackage($package);
                $total += (float)$package->getPackagePrice();
            }
        }

        // Food
        $foodIds = $request->request->all('food_ids');
        $foodQtys = $request->request->all('food_qtys');
        $foodItems = [];
        
        foreach ($foodIds as $index => $foodId) {
            if ($foodId) {
                $food = $this->foodRepo->find($foodId);
                if ($food) {
                    $qty = (int)($foodQtys[$index] ?? 1);
                    $foodItems[$foodId] = $qty;
                    $total += (float)$food->getPrice() * $qty;
                }
            }
        }
        $reservation->setFoodItems($foodItems);

        $reservation->setTotalAmount(number_format($total, 2, '.', ''));

        return $reservation;
    }

    /**
     * Get food details from JSON
     */
    private function getFoodDetails(Reservation $reservation): array
    {
        $details = [];
        $foodItems = $reservation->getFoodItems() ?? [];

        foreach ($foodItems as $foodId => $qty) {
            $food = $this->foodRepo->find($foodId);
            if ($food) {
                $details[] = [
                    'food' => $food,
                    'quantity' => $qty,
                    'subtotal' => (float)$food->getPrice() * $qty
                ];
            }
        }

        return $details;
    }

    /**
     * Check if current user owns the reservation
     */
    private function checkOwnership(Reservation $reservation): void
    {
        if ($reservation->getGuest() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You do not have access to this reservation.');
        }
    }
}