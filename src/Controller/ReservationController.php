<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Repository\FoodRepository;
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
        private FoodRepository $foodRepo
    ) {}

    /**
     * List all reservations with filters
     */
    #[Route('', name: 'reservation_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filter = $request->query->get('filter', 'all');

        // Try to get real data, fallback to dummy
        try {
            $reservations = match($filter) {
                'pending' => $this->reservationRepo->findByStatus(Reservation::STATUS_PENDING),
                'confirmed' => $this->reservationRepo->findByStatus(Reservation::STATUS_CONFIRMED),
                'cancelled' => $this->reservationRepo->findByStatus(Reservation::STATUS_CANCELLED),
                'completed' => $this->reservationRepo->findByStatus(Reservation::STATUS_COMPLETED),
                'paid_pending' => $this->reservationRepo->findPaidPending(),
                default => $this->reservationRepo->findAllWithDetails(),
            };
            
            // If no real data, use dummy
            if (empty($reservations)) {
                $reservations = $this->getDummyReservations();
            }
        } catch (\Exception $e) {
            // Use dummy data if database fails
            $reservations = $this->getDummyReservations();
        }

        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations,
            'filter' => $filter,
        ]);
    }

    /**
     * View single reservation details - FIXED ROUTE with {id}
     */
    #[Route('/{id}', name: 'reservation_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        // Try to find real reservation
        $reservation = $this->reservationRepo->find($id);
        
        // If not found, use dummy data
        if (!$reservation) {
            $dummyData = $this->getDummyReservationById($id);
            $foodDetails = $dummyData['foodDetails'] ?? [];
            
            return $this->render('reservation/show.html.twig', [
                'reservation' => $dummyData['reservation'],
                'foodDetails' => $foodDetails,
                'isDummy' => true,
            ]);
        }

        $foodDetails = $this->getFoodDetails($reservation);

        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
            'foodDetails' => $foodDetails,
            'isDummy' => false,
        ]);
    }

    /**
     * Mark reservation as PAID
     */
    #[Route('/{id}/mark-paid', name: 'reservation_mark_paid', methods: ['POST'])]
    public function markPaid(Request $request, int $id): Response
    {
        $reservation = $this->reservationRepo->find($id);
        
        if ($reservation && $this->isCsrfTokenValid('paid' . $id, $request->request->get('_token'))) {
            $reservation->setPaymentStatus(Reservation::PAYMENT_PAID);
            $this->em->flush();
            $this->addFlash('success', 'Reservation marked as PAID!');
        } else {
            $this->addFlash('success', '(Demo) Reservation marked as PAID!');
        }

        return $this->redirectToRoute('reservation_show', ['id' => $id]);
    }

    /**
     * Approve reservation
     */
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
        } else {
            $this->addFlash('success', '(Demo) Reservation APPROVED successfully!');
        }

        return $this->redirectToRoute('reservation_show', ['id' => $id]);
    }

    /**
     * Reject reservation
     */
    #[Route('/{id}/reject', name: 'reservation_reject', methods: ['POST'])]
    public function reject(Request $request, int $id): Response
    {
        $reservation = $this->reservationRepo->find($id);
        
        if ($reservation && $this->isCsrfTokenValid('reject' . $id, $request->request->get('_token'))) {
            $reservation->setStatus(Reservation::STATUS_CANCELLED);
            $reservation->setAdminNotes($request->request->get('reason'));
            $this->em->flush();
            $this->addFlash('warning', 'Reservation has been REJECTED.');
        } else {
            $this->addFlash('warning', '(Demo) Reservation has been REJECTED.');
        }

        return $this->redirectToRoute('reservation_show', ['id' => $id]);
    }

    /**
     * Mark reservation as completed
     */
    #[Route('/{id}/complete', name: 'reservation_complete', methods: ['POST'])]
    public function complete(Request $request, int $id): Response
    {
        $reservation = $this->reservationRepo->find($id);
        
        if ($reservation && $this->isCsrfTokenValid('complete' . $id, $request->request->get('_token'))) {
            $reservation->setStatus(Reservation::STATUS_COMPLETED);
            $this->em->flush();
            $this->addFlash('success', 'Reservation marked as COMPLETED!');
        } else {
            $this->addFlash('success', '(Demo) Reservation marked as COMPLETED!');
        }

        return $this->redirectToRoute('reservation_show', ['id' => $id]);
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
     * Generate dummy reservations for demo purposes
     */
    private function getDummyReservations(): array
    {
        return [
            [
                'id' => 1,
                'reservationCode' => 'RES-2024-001234',
                'createdAt' => new \DateTime('2024-01-15'),
                'guestEmail' => 'maria.santos@gmail.com',
                'guestPhone' => '+63 917 123 4567',
                'hasRoom' => true,
                'hasTour' => false,
                'hasPackage' => false,
                'hasFood' => true,
                'totalPrice' => 12500.00,
                'paymentStatus' => 'paid',
                'status' => 'confirmed',
            ],
            [
                'id' => 2,
                'reservationCode' => 'RES-2024-001235',
                'createdAt' => new \DateTime('2024-01-16'),
                'guestEmail' => 'juan.dela.cruz@yahoo.com',
                'guestPhone' => '+63 918 234 5678',
                'hasRoom' => false,
                'hasTour' => true,
                'hasPackage' => false,
                'hasFood' => false,
                'totalPrice' => 3500.00,
                'paymentStatus' => 'unpaid',
                'status' => 'pending',
            ],
            [
                'id' => 3,
                'reservationCode' => 'RES-2024-001236',
                'createdAt' => new \DateTime('2024-01-17'),
                'guestEmail' => 'ana.reyes@outlook.com',
                'guestPhone' => '+63 919 345 6789',
                'hasRoom' => false,
                'hasTour' => false,
                'hasPackage' => true,
                'hasFood' => false,
                'totalPrice' => 25000.00,
                'paymentStatus' => 'paid',
                'status' => 'completed',
            ],
            [
                'id' => 4,
                'reservationCode' => 'RES-2024-001237',
                'createdAt' => new \DateTime('2024-01-18'),
                'guestEmail' => 'pedro.lopez@gmail.com',
                'guestPhone' => '+63 920 456 7890',
                'hasRoom' => true,
                'hasTour' => true,
                'hasPackage' => false,
                'hasFood' => true,
                'totalPrice' => 18750.00,
                'paymentStatus' => 'unpaid',
                'status' => 'cancelled',
            ],
            [
                'id' => 5,
                'reservationCode' => 'RES-2024-001238',
                'createdAt' => new \DateTime('2024-01-19'),
                'guestEmail' => 'rosa.garcia@hotmail.com',
                'guestPhone' => '+63 921 567 8901',
                'hasRoom' => true,
                'hasTour' => false,
                'hasPackage' => false,
                'hasFood' => false,
                'totalPrice' => 8200.00,
                'paymentStatus' => 'paid',
                'status' => 'pending',
            ],
        ];
    }

    /**
     * Get dummy reservation by ID for show page
     */
    private function getDummyReservationById(int $id): array
    {
        $dummyList = $this->getDummyReservations();
        
        foreach ($dummyList as $dummy) {
            if ($dummy['id'] === $id) {
                return [
                    'reservation' => $this->buildDummyReservationObject($dummy),
                    'foodDetails' => $dummy['hasFood'] ? $this->getDummyFoodDetails() : [],
                ];
            }
        }

        // Default dummy if ID not found
        return [
            'reservation' => $this->buildDummyReservationObject($dummyList[0]),
            'foodDetails' => $this->getDummyFoodDetails(),
        ];
    }

    /**
     * Build a dummy reservation object (stdClass to mimic entity)
     */
    private function buildDummyReservationObject(array $data): object
    {
        $reservation = new \stdClass();
        $reservation->id = $data['id'];
        $reservation->reservationCode = $data['reservationCode'];
        $reservation->createdAt = $data['createdAt'];
        $reservation->updatedAt = new \DateTime();
        $reservation->status = strtoupper($data['status']);
        $reservation->paymentStatus = strtoupper($data['paymentStatus']);
        $reservation->totalAmount = $data['totalPrice'];
        $reservation->contactPhone = $data['guestPhone'];
        $reservation->numberOfGuests = rand(1, 4);
        $reservation->specialRequests = 'Please prepare the room early. We will be arriving before noon.';
        
        // Guest info
        $guest = new \stdClass();
        $guest->email = $data['guestEmail'];
        $reservation->guest = $guest;

        // Status helpers
        $reservation->isPending = ($data['status'] === 'pending');
        $reservation->isConfirmed = ($data['status'] === 'confirmed');
        $reservation->isPaid = ($data['paymentStatus'] === 'paid');

        // Room
        if ($data['hasRoom']) {
            $room = new \stdClass();
            $room->roomNumber = 'R-' . rand(101, 110);
            $room->roomType = ['Deluxe', 'Superior', 'Standard'][rand(0, 2)];
            $room->pricePerNight = [2500.00, 3500.00, 1800.00][rand(0, 2)];
            $reservation->room = $room;
            $reservation->checkInDate = new \DateTime('+3 days');
            $reservation->checkOutDate = new \DateTime('+5 days');
            $reservation->nights = 2;
        } else {
            $reservation->room = null;
        }

        // Tour
        if ($data['hasTour']) {
            $tour = new \stdClass();
            $tour->name = ['Island Hopping', 'Mountain Trek', 'City Tour'][rand(0, 2)];
            $tour->location = ['Boracay', 'Baguio', 'Manila'][rand(0, 2)];
            $tour->price = [1500.00, 2000.00, 1200.00][rand(0, 2)];
            $reservation->tour = $tour;
            $reservation->tourDate = new \DateTime('+7 days');
            $reservation->tourParticipants = rand(2, 6);
        } else {
            $reservation->tour = null;
        }

        // Package
        if ($data['hasPackage']) {
            $package = new \stdClass();
            $package->name = ['Honeymoon Package', 'Family Bundle', 'Adventure Pack'][rand(0, 2)];
            $package->packagePrice = [25000.00, 35000.00, 15000.00][rand(0, 2)];
            $package->description = 'All-inclusive package with meals, tours, and accommodation.';
            $reservation->package = $package;
        } else {
            $reservation->package = null;
        }

        // Approval info (for confirmed/completed)
        if (in_array($data['status'], ['confirmed', 'completed'])) {
            $approvedBy = new \stdClass();
            $approvedBy->email = 'admin@lacasa.com';
            $reservation->approvedBy = $approvedBy;
            $reservation->approvedAt = new \DateTime('-1 day');
            $reservation->adminNotes = 'Approved after payment verification.';
        } else {
            $reservation->approvedBy = null;
            $reservation->approvedAt = null;
            $reservation->adminNotes = null;
        }

        return $reservation;
    }

    /**
     * Get dummy food details
     */
    private function getDummyFoodDetails(): array
    {
        $foods = [
            ['name' => 'Chicken Adobo', 'category' => 'Main Course', 'price' => 280.00],
            ['name' => 'Sinigang na Baboy', 'category' => 'Main Course', 'price' => 320.00],
            ['name' => 'Garlic Rice', 'category' => 'Rice', 'price' => 50.00],
            ['name' => 'Halo-Halo', 'category' => 'Dessert', 'price' => 120.00],
        ];

        $details = [];
        $numItems = rand(2, 4);

        for ($i = 0; $i < $numItems; $i++) {
            $food = new \stdClass();
            $food->name = $foods[$i]['name'];
            $food->category = $foods[$i]['category'];
            $food->price = $foods[$i]['price'];
            
            $qty = rand(1, 3);
            $details[] = [
                'food' => $food,
                'quantity' => $qty,
                'subtotal' => $food->price * $qty,
            ];
        }

        return $details;
    }
}