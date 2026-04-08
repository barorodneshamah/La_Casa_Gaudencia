<?php

namespace App\Controller;

use App\Repository\RoomRepository;
use App\Repository\TourRepository;
use App\Repository\FoodRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/services', name: 'api_services_')]
class ServiceApiController extends AbstractController
{
    #[Route('/rooms', name: 'rooms', methods: ['GET'])]
    public function getRooms(RoomRepository $roomRepository): JsonResponse
    {
        try {
            $allRooms = $roomRepository->findBy(['status' => 'Available']);
            $totalRooms = count($allRooms);

            if ($totalRooms > 0) {
                shuffle($allRooms);
                $rooms = array_slice($allRooms, 0, 4);
            } else {
                $rooms = [];
            }

            $data = [];
            foreach ($rooms as $room) {
                $image = $this->buildImagePath($room->getMainImage(), 'rooms');

                $features = $room->getFeatures();
                if ($features) {
                    $amenities = array_map('trim', explode(',', $features));
                    $amenities = array_filter($amenities);
                    $amenities = array_values($amenities);
                } else {
                    $amenities = ['WiFi', 'AC', 'TV', 'Hot Water'];
                }

                $data[] = [
                    'id' => $room->getId(),
                    'name' => $room->getRoomType() . ' - Room ' . $room->getRoomNumber(),
                    'description' => $room->getDescription() ?? 'Comfortable ' . strtolower($room->getRoomType()) . ' with modern amenities.',
                    'price' => (float) $room->getPricePerNight(),
                    'image' => $image,
                    'capacity' => $room->getCapacity(),
                    'amenities' => array_slice($amenities, 0, 4),
                    'available' => rand(1, 5),
                    'rating' => round(rand(42, 50) / 10, 1),
                    'reviews' => rand(20, 150),
                    'category' => strtolower(str_replace(' ', '-', $room->getRoomType()))
                ];
            }

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'total' => $totalRooms,
                'showing' => count($data),
                'minPrice' => $totalRooms > 0 && count($data) > 0 ? (float) min(array_column($data, 'price')) : 0
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'data' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/tours', name: 'tours', methods: ['GET'])]
    public function getTours(TourRepository $tourRepository): JsonResponse
    {
        try {
            $allTours = $tourRepository->findBy(['status' => 'Available']);
            $totalTours = count($allTours);

            if ($totalTours > 0) {
                shuffle($allTours);
                $tours = array_slice($allTours, 0, 4);
            } else {
                $tours = [];
            }

            $data = [];
            foreach ($tours as $tour) {
                $image = $this->buildImagePath($tour->getMainImage(), 'tours');

                $data[] = [
                    'id' => $tour->getId(),
                    'name' => $tour->getName(),
                    'description' => $tour->getDescription() ?? 'Amazing tour experience in ' . $tour->getLocation() . '.',
                    'price' => (float) $tour->getPrice(),
                    'image' => $image,
                    'duration' => $tour->getDuration(),
                    'location' => $tour->getLocation(),
                    'maxGuests' => $tour->getAvailableSlots(),
                    'category' => 'adventure',
                    'rating' => round(rand(43, 50) / 10, 1),
                    'reviews' => rand(30, 200),
                    'schedule' => $tour->getScheduleDate() 
                        ? $tour->getScheduleDate()->format('l, g:i A') 
                        : 'Daily: 8AM & 2PM',
                    'inclusions' => ['Tour Guide', 'Transport', 'Insurance']
                ];
            }

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'total' => $totalTours,
                'showing' => count($data),
                'minPrice' => $totalTours > 0 && count($data) > 0 ? (float) min(array_column($data, 'price')) : 0
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'data' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/food', name: 'food', methods: ['GET'])]
    public function getFood(FoodRepository $foodRepository): JsonResponse
    {
        try {
            $allFood = $foodRepository->findBy(['status' => 'Available']);
            $totalItems = count($allFood);

            if ($totalItems > 0) {
                shuffle($allFood);
                $foodItems = array_slice($allFood, 0, 4);
            } else {
                $foodItems = [];
            }

            $data = [];
            foreach ($foodItems as $food) {
                // Use 'foods' folder (with 's') to match your actual folder name
                $image = $this->buildImagePath($food->getMainImage(), 'foods');

                $data[] = [
                    'id' => $food->getId(),
                    'name' => $food->getName(),
                    'description' => $food->getDescription() ?? 'Delicious ' . strtolower($food->getCategory() ?? 'Filipino') . ' cuisine.',
                    'price' => (float) $food->getPrice(),
                    'image' => $image,
                    'category' => $food->getCategory() ?? 'Filipino',
                    'rating' => round(rand(42, 49) / 10, 1),
                    'reviews' => rand(15, 100),
                    'isVegetarian' => false,
                    'isSpicy' => false,
                    'availableStock' => $food->getAvailableStock()
                ];
            }

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'total' => $totalItems,
                'showing' => count($data),
                'minPrice' => $totalItems > 0 && count($data) > 0 ? (float) min(array_column($data, 'price')) : 0
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'data' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build image path - supports all image formats (jpg, jpeg, png, gif, webp, svg, etc.)
     */
    private function buildImagePath(?string $dbImage, string $type): string
    {
        // Default images for each type
        $defaults = [
            'rooms' => '/Images/room1.jpg',
            'tours' => '/Images/tour1.jpg',
            'foods' => '/Images/food1.jpg'
        ];

        // If no image in database, return default
        if (!$dbImage || empty(trim($dbImage))) {
            return $defaults[$type] ?? '/Images/placeholder.jpg';
        }

        $dbImage = trim($dbImage);

        // If it's already a full URL (http/https), use as-is
        if (str_starts_with($dbImage, 'http://') || str_starts_with($dbImage, 'https://')) {
            return $dbImage;
        }

        // If it already starts with /uploads or /images, use as-is
        if (str_starts_with($dbImage, '/uploads/') || str_starts_with($dbImage, '/images/') || str_starts_with($dbImage, '/Images/')) {
            return $dbImage;
        }

        // If it starts with uploads/ (no leading slash), add the slash
        if (str_starts_with($dbImage, 'uploads/')) {
            return '/' . $dbImage;
        }

        // Build the path with the type folder
        // Type should match your actual folder names: rooms, tours, foods
        return '/uploads/' . $type . '/' . $dbImage;
    }
}