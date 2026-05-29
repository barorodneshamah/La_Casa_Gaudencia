<?php

namespace App\Controller;

use App\Repository\RoomRepository;
use App\Repository\TourRepository;
use App\Repository\FoodRepository;
use App\Repository\PackageRepository;
use App\Repository\SpaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/services', name: 'api_services_')]
class ServiceApiController extends AbstractController
{
    #[Route('/rooms', name: 'rooms', methods: ['GET'])]
    public function getRooms(RoomRepository $roomRepository, Request $request): JsonResponse
    {
        try {
            $base = $request->getSchemeAndHttpHost();
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
                $image = $this->buildImagePath($room->getMainImage(), 'rooms', $base);

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
                    'available' => $room->getCapacity(),
                    'rating' => round(4.0 + ($room->getId() % 10) / 10, 1),
                    'reviews' => 20 + ($room->getId() * 7 % 130),
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
    public function getTours(TourRepository $tourRepository, Request $request): JsonResponse
    {
        try {
            $base = $request->getSchemeAndHttpHost();
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
                $image = $this->buildImagePath($tour->getMainImage(), 'tours', $base);

                $data[] = [
                    'id' => $tour->getId(),
                    'name' => $tour->getName(),
                    'description' => $tour->getDescription() ?? 'Amazing tour experience in ' . $tour->getLocation() . '.',
                    'price' => (float) $tour->getPrice(),
                    'image' => $image,
                    'duration' => $tour->getDuration(),
                    'location' => $tour->getLocation(),
                    'maxGuests' => $tour->getAvailableSlots(),
                    'available' => $tour->getAvailableSlots(),
                    'category' => 'adventure',
                    'rating' => round(4.3 + ($tour->getId() % 8) / 10, 1),
                    'reviews' => 30 + ($tour->getId() * 11 % 170),
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
    public function getFood(FoodRepository $foodRepository, Request $request): JsonResponse
    {
        try {
            $base = $request->getSchemeAndHttpHost();
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
                $image = $this->buildImagePath($food->getMainImage(), 'foods', $base);

                $data[] = [
                    'id' => $food->getId(),
                    'name' => $food->getName(),
                    'description' => $food->getDescription() ?? 'Delicious ' . strtolower($food->getCategory() ?? 'Filipino') . ' cuisine.',
                    'price' => (float) $food->getPrice(),
                    'image' => $image,
                    'category' => $food->getCategory() ?? 'Filipino',
                    'rating' => round(4.2 + ($food->getId() % 8) / 10, 1),
                    'reviews' => 15 + ($food->getId() * 9 % 85),
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

    #[Route('/packages', name: 'packages', methods: ['GET'])]
    public function getPackages(PackageRepository $packageRepository, Request $request): JsonResponse
    {
        try {
            $base = $request->getSchemeAndHttpHost();
            $allPackages = $packageRepository->findBy(['status' => 'Active']);
            $totalPackages = count($allPackages);

            if ($totalPackages > 0) {
                shuffle($allPackages);
                $packages = array_slice($allPackages, 0, 4);
            } else {
                $packages = [];
            }

            $data = [];
            foreach ($packages as $package) {
                $image = $this->buildImagePath($package->getMainImage(), 'packages', $base);

                $inclusions = [];
                if ($package->getInclusions()) {
                    $inclusions = array_values(array_filter(array_map('trim', explode(',', $package->getInclusions()))));
                }

                $remaining = $package->getMaxRedemptions() !== null
                    ? $package->getMaxRedemptions() - $package->getCurrentRedemptions()
                    : null;

                $data[] = [
                    'id'                 => $package->getId(),
                    'name'               => $package->getName(),
                    'description'        => $package->getDescription() ?? 'Enjoy our exclusive ' . $package->getName() . ' package.',
                    'price'              => (float) $package->getPackagePrice(),
                    'originalPrice'      => $package->getOriginalPrice() ? (float) $package->getOriginalPrice() : null,
                    'discountPercentage' => $package->getDiscountPercentage() ? (float) $package->getDiscountPercentage() : null,
                    'image'              => $image,
                    'packageType'        => $package->getPackageType(),
                    'durationDays'       => $package->getDurationDays(),
                    'durationNights'     => $package->getDurationNights(),
                    'maxGuests'          => $package->getMaxGuests(),
                    'inclusions'         => array_slice($inclusions, 0, 5),
                    'remainingSlots'     => $remaining,
                    'validUntil'         => $package->getValidUntil()?->format('Y-m-d'),
                    'rating'             => round(4.3 + ($package->getId() % 8) / 10, 1),
                    'reviews'            => 25 + ($package->getId() * 13 % 120),
                ];
            }

            return new JsonResponse([
                'success'  => true,
                'data'     => $data,
                'total'    => $totalPackages,
                'showing'  => count($data),
                'minPrice' => $totalPackages > 0 && count($data) > 0 ? (float) min(array_column($data, 'price')) : 0,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'data'    => [],
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/spas', name: 'spas', methods: ['GET'])]
    public function getSpas(SpaRepository $spaRepository, Request $request): JsonResponse
    {
        try {
            $base = $request->getSchemeAndHttpHost();
            $allSpas = $spaRepository->findBy(['status' => 'Available']);
            $totalSpas = count($allSpas);

            if ($totalSpas > 0) {
                shuffle($allSpas);
                $spas = array_slice($allSpas, 0, 4);
            } else {
                $spas = [];
            }

            $data = [];
            foreach ($spas as $spa) {
                $image = $this->buildImagePath($spa->getMainImage(), 'spas', $base);

                $data[] = [
                    'id'          => $spa->getId(),
                    'name'        => $spa->getName(),
                    'description' => $spa->getDescription() ?? 'Relaxing ' . strtolower($spa->getCategory() ?? 'spa') . ' experience.',
                    'price'       => (float) $spa->getPrice(),
                    'image'       => $image,
                    'category'    => $spa->getCategory() ?? 'Wellness',
                    'duration'    => $spa->getDuration(),
                    'capacity'    => $spa->getCapacity(),
                    'rating'      => round(4.4 + ($spa->getId() % 6) / 10, 1),
                    'reviews'     => 20 + ($spa->getId() * 8 % 100),
                ];
            }

            return new JsonResponse([
                'success'  => true,
                'data'     => $data,
                'total'    => $totalSpas,
                'showing'  => count($data),
                'minPrice' => $totalSpas > 0 && count($data) > 0 ? (float) min(array_column($data, 'price')) : 0,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'data'    => [],
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    private function buildImagePath(?string $dbImage, string $type, string $base = ''): string
    {
        $defaults = [
            'rooms'    => '/images/bedroom.jpg',
            'tours'    => '/images/oslob.jpg',
            'foods'    => '/images/food.jpg',
            'spas'     => '/images/ground.jpeg',
            'packages' => '/images/Hotel.jpg',
        ];

        if (!$dbImage || empty(trim($dbImage))) {
            $path = $defaults[$type] ?? '/images/placeholder.jpg';
            return $base . $path;
        }

        $dbImage = trim($dbImage);

        if (str_starts_with($dbImage, 'http://') || str_starts_with($dbImage, 'https://')) {
            return $dbImage;
        }

        if (str_starts_with($dbImage, '/')) {
            return $base . $dbImage;
        }

        if (str_starts_with($dbImage, 'uploads/')) {
            return $base . '/' . $dbImage;
        }

        return $base . '/uploads/' . $type . '/' . $dbImage;
    }
}