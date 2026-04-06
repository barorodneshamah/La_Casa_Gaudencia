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
    /**
     * Get rooms data for landing page
     */
    #[Route('/rooms', name: 'rooms', methods: ['GET'])]
    public function getRooms(RoomRepository $roomRepository): JsonResponse
    {
        try {
            // Get limited rooms for teaser (only 4 to encourage sign-up)
            $rooms = $roomRepository->findForLandingPage(4);
            $totalRooms = $roomRepository->countActiveRooms();
            $minPrice = $roomRepository->findMinPrice();

            // Transform room data
            $data = array_map(function($room) {
                return [
                    'id' => $room->getId(),
                    'name' => $room->getName(),
                    'description' => $this->truncateText($room->getDescription(), 120),
                    'price' => (float) $room->getPrice(),
                    'image' => $this->getRoomImage($room),
                    'capacity' => $room->getCapacity() ?? 2,
                    'amenities' => $this->getAmenities($room),
                    'available' => $this->getAvailableCount($room),
                    'rating' => $this->getRating($room),
                    'reviews' => $this->getReviewCount($room),
                    'category' => $this->getRoomCategory($room)
                ];
            }, $rooms);

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'minPrice' => $minPrice ?? 800,
                'total' => $totalRooms,
                'showing' => count($data),
                'message' => count($data) > 0 
                    ? "Showing " . count($data) . " of {$totalRooms} available rooms. Sign in to see all!" 
                    : "No rooms available at the moment. Check back soon!"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to load rooms. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tours data for landing page
     */
    #[Route('/tours', name: 'tours', methods: ['GET'])]
    public function getTours(TourRepository $tourRepository): JsonResponse
    {
        try {
            // Get limited tours for teaser
            $tours = $tourRepository->findForLandingPage(4);
            $totalTours = $tourRepository->countActiveTours();
            $minPrice = $tourRepository->findMinPrice();

            // Transform tour data
            $data = array_map(function($tour) {
                return [
                    'id' => $tour->getId(),
                    'name' => $tour->getName(),
                    'description' => $this->truncateText($tour->getDescription(), 120),
                    'price' => (float) $tour->getPrice(),
                    'image' => $this->getTourImage($tour),
                    'duration' => $this->getTourDuration($tour),
                    'maxGuests' => $tour->getMaxGuests() ?? 12,
                    'category' => $this->getTourCategory($tour),
                    'rating' => $this->getRating($tour),
                    'reviews' => $this->getReviewCount($tour),
                    'schedule' => $this->getTourSchedule($tour),
                    'inclusions' => $this->getTourInclusions($tour)
                ];
            }, $tours);

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'minPrice' => $minPrice ?? 1200,
                'total' => $totalTours,
                'showing' => count($data),
                'message' => count($data) > 0 
                    ? "Showing " . count($data) . " of {$totalTours} amazing tours. Sign in to explore all!" 
                    : "No tours available at the moment. Check back soon!"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to load tours. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get food items data for landing page
     */
    #[Route('/food', name: 'food', methods: ['GET'])]
    public function getFood(FoodRepository $foodRepository): JsonResponse
    {
        try {
            // Get limited food items for teaser
            $foodItems = $foodRepository->findForLandingPage(4);
            $totalItems = $foodRepository->countActiveFoodItems();
            $minPrice = $foodRepository->findMinPrice();

            // Transform food data
            $data = array_map(function($food) {
                return [
                    'id' => $food->getId(),
                    'name' => $food->getName(),
                    'description' => $this->truncateText($food->getDescription(), 100),
                    'price' => (float) $food->getPrice(),
                    'image' => $this->getFoodImage($food),
                    'category' => $this->getFoodCategory($food),
                    'rating' => $this->getRating($food),
                    'reviews' => $this->getReviewCount($food),
                    'isVegetarian' => $this->isVegetarian($food),
                    'isSpicy' => $this->isSpicy($food),
                    'servingSize' => $this->getServingSize($food)
                ];
            }, $foodItems);

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'minPrice' => $minPrice ?? 150,
                'total' => $totalItems,
                'showing' => count($data),
                'message' => count($data) > 0 
                    ? "Showing " . count($data) . " of {$totalItems} delicious dishes. Sign in to see full menu!" 
                    : "No food items available at the moment. Check back soon!"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to load menu. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       HELPER METHODS - Adjust these based on your entity structure
    ═══════════════════════════════════════════════════════════════ */

    /**
     * Truncate text to specific length
     */
    private function truncateText(?string $text, int $length = 120): string
    {
        if (!$text || trim($text) === '') {
            return 'No description available.';
        }
        
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . '...';
    }

    /**
     * Get room image path
     */
    private function getRoomImage($room): string
    {
        // Adjust based on your Room entity
        if (method_exists($room, 'getImagePath') && $room->getImagePath()) {
            return '/uploads/rooms/' . $room->getImagePath();
        }
        if (method_exists($room, 'getImage') && $room->getImage()) {
            return '/uploads/rooms/' . $room->getImage();
        }
        if (method_exists($room, 'getPhoto') && $room->getPhoto()) {
            return '/uploads/rooms/' . $room->getPhoto();
        }
        
        // Default fallback images based on category
        $category = $this->getRoomCategory($room);
        $defaultImages = [
            'suite' => 'Images/room2.jpg',
            'deluxe' => 'Images/room1.jpg',
            'budget' => 'Images/room3.jpg',
        ];
        
        return $defaultImages[$category] ?? 'Images/room1.jpg';
    }

    /**
     * Get tour image path
     */
    private function getTourImage($tour): string
    {
        // Adjust based on your Tour entity
        if (method_exists($tour, 'getImagePath') && $tour->getImagePath()) {
            return '/uploads/tours/' . $tour->getImagePath();
        }
        if (method_exists($tour, 'getImage') && $tour->getImage()) {
            return '/uploads/tours/' . $tour->getImage();
        }
        if (method_exists($tour, 'getPhoto') && $tour->getPhoto()) {
            return '/uploads/tours/' . $tour->getPhoto();
        }
        
        // Default fallback
        $category = $this->getTourCategory($tour);
        $defaultImages = [
            'cultural' => 'Images/tour1.jpg',
            'adventure' => 'Images/tour2.jpg',
            'food' => 'Images/tour1.jpg',
        ];
        
        return $defaultImages[$category] ?? 'Images/tour1.jpg';
    }

    /**
     * Get food image path
     */
    private function getFoodImage($food): string
    {
        // Adjust based on your Food entity
        if (method_exists($food, 'getImagePath') && $food->getImagePath()) {
            return '/uploads/food/' . $food->getImagePath();
        }
        if (method_exists($food, 'getImage') && $food->getImage()) {
            return '/uploads/food/' . $food->getImage();
        }
        if (method_exists($food, 'getPhoto') && $food->getPhoto()) {
            return '/uploads/food/' . $food->getPhoto();
        }
        
        return 'Images/food1.jpg';
    }

    /**
     * Get room amenities
     */
    private function getAmenities($room): array
    {
        $amenities = [];
        
        // Check for amenities methods in your Room entity
        if (method_exists($room, 'getAmenities') && $room->getAmenities()) {
            return is_array($room->getAmenities()) 
                ? $room->getAmenities() 
                : explode(',', $room->getAmenities());
        }
        
        // Check individual amenity flags
        if (method_exists($room, 'getHasWifi') || method_exists($room, 'hasWifi')) {
            $hasWifi = method_exists($room, 'getHasWifi') ? $room->getHasWifi() : $room->hasWifi();
            if ($hasWifi) $amenities[] = 'WiFi';
        }
        
        if (method_exists($room, 'getHasAC') || method_exists($room, 'hasAC')) {
            $hasAC = method_exists($room, 'getHasAC') ? $room->getHasAC() : $room->hasAC();
            if ($hasAC) $amenities[] = 'AC';
        }
        
        if (method_exists($room, 'getHasTV') || method_exists($room, 'hasTV')) {
            $hasTV = method_exists($room, 'getHasTV') ? $room->getHasTV() : $room->hasTV();
            if ($hasTV) $amenities[] = 'Smart TV';
        }
        
        if (method_exists($room, 'getHasBreakfast') || method_exists($room, 'hasBreakfast')) {
            $hasBreakfast = method_exists($room, 'getHasBreakfast') ? $room->getHasBreakfast() : $room->hasBreakfast();
            if ($hasBreakfast) $amenities[] = 'Breakfast';
        }

        // Default amenities if none found
        if (empty($amenities)) {
            $amenities = ['WiFi', 'AC', 'TV', 'Breakfast'];
        }

        return $amenities;
    }

    /**
     * Get available count
     */
    private function getAvailableCount($room): int
    {
        if (method_exists($room, 'getAvailableCount')) {
            return $room->getAvailableCount() ?? rand(1, 5);
        }
        if (method_exists($room, 'getQuantity')) {
            return $room->getQuantity() ?? rand(1, 5);
        }
        if (method_exists($room, 'getStock')) {
            return $room->getStock() ?? rand(1, 5);
        }
        
        // Random for demo purposes
        return rand(1, 5);
    }

    /**
     * Get rating
     */
    private function getRating($entity): float
    {
        if (method_exists($entity, 'getAverageRating')) {
            return (float) ($entity->getAverageRating() ?? 4.5);
        }
        if (method_exists($entity, 'getRating')) {
            return (float) ($entity->getRating() ?? 4.5);
        }
        
        // Default good rating
        return 4.5;
    }

    /**
     * Get review count
     */
    private function getReviewCount($entity): int
    {
        if (method_exists($entity, 'getReviewCount')) {
            return $entity->getReviewCount() ?? 0;
        }
        if (method_exists($entity, 'getReviews') && is_countable($entity->getReviews())) {
            return count($entity->getReviews());
        }
        
        // Random for demo
        return rand(10, 200);
    }

    /**
     * Get room category
     */
    private function getRoomCategory($room): string
    {
        if (method_exists($room, 'getCategory')) {
            $category = strtolower($room->getCategory() ?? 'deluxe');
            return in_array($category, ['budget', 'deluxe', 'suite']) ? $category : 'deluxe';
        }
        if (method_exists($room, 'getType')) {
            $type = strtolower($room->getType() ?? 'deluxe');
            return in_array($type, ['budget', 'deluxe', 'suite']) ? $type : 'deluxe';
        }
        
        // Categorize by price
        $price = $room->getPrice();
        if ($price < 1500) return 'budget';
        if ($price > 4000) return 'suite';
        return 'deluxe';
    }

    /**
     * Get tour category
     */
    private function getTourCategory($tour): string
    {
        if (method_exists($tour, 'getCategory')) {
            $category = strtolower($tour->getCategory() ?? 'cultural');
            return in_array($category, ['cultural', 'adventure', 'food']) ? $category : 'cultural';
        }
        if (method_exists($tour, 'getType')) {
            $type = strtolower($tour->getType() ?? 'cultural');
            return in_array($type, ['cultural', 'adventure', 'food']) ? $type : 'cultural';
        }
        
        return 'cultural';
    }

    /**
     * Get food category
     */
    private function getFoodCategory($food): string
    {
        if (method_exists($food, 'getCategory')) {
            return $food->getCategory() ?? 'Filipino';
        }
        if (method_exists($food, 'getType')) {
            return $food->getType() ?? 'Filipino';
        }
        
        return 'Filipino';
    }

    /**
     * Get tour duration
     */
    private function getTourDuration($tour): string
    {
        if (method_exists($tour, 'getDuration')) {
            return $tour->getDuration() ?? '3 Hours';
        }
        if (method_exists($tour, 'getHours')) {
            $hours = $tour->getHours();
            return $hours ? "{$hours} Hours" : '3 Hours';
        }
        
        return '3 Hours';
    }

    /**
     * Get tour schedule
     */
    private function getTourSchedule($tour): string
    {
        if (method_exists($tour, 'getSchedule')) {
            return $tour->getSchedule() ?? 'Daily: 8AM, 2PM';
        }
        if (method_exists($tour, 'getAvailability')) {
            return $tour->getAvailability() ?? 'Daily: 8AM, 2PM';
        }
        
        return 'Daily: 8AM, 2PM';
    }

    /**
     * Get tour inclusions
     */
    private function getTourInclusions($tour): array
    {
        $inclusions = [];
        
        if (method_exists($tour, 'getInclusions') && $tour->getInclusions()) {
            return is_array($tour->getInclusions()) 
                ? $tour->getInclusions() 
                : explode(',', $tour->getInclusions());
        }
        
        // Check individual inclusion flags
        if (method_exists($tour, 'getIncludesTransport') && $tour->getIncludesTransport()) {
            $inclusions[] = 'Transport';
        }
        if (method_exists($tour, 'getIncludesMeals') && $tour->getIncludesMeals()) {
            $inclusions[] = 'Meals';
        }
        if (method_exists($tour, 'getIncludesGuide') && $tour->getIncludesGuide()) {
            $inclusions[] = 'Tour Guide';
        }

        // Default inclusions
        if (empty($inclusions)) {
            $inclusions = ['Guide', 'Transport'];
        }

        return $inclusions;
    }

    /**
     * Check if food is vegetarian
     */
    private function isVegetarian($food): bool
    {
        if (method_exists($food, 'getIsVegetarian')) {
            return (bool) $food->getIsVegetarian();
        }
        if (method_exists($food, 'isVegetarian')) {
            return (bool) $food->isVegetarian();
        }
        
        return false;
    }

    /**
     * Check if food is spicy
     */
    private function isSpicy($food): bool
    {
        if (method_exists($food, 'getIsSpicy')) {
            return (bool) $food->getIsSpicy();
        }
        if (method_exists($food, 'isSpicy')) {
            return (bool) $food->isSpicy();
        }
        
        return false;
    }

    /**
     * Get serving size
     */
    private function getServingSize($food): string
    {
        if (method_exists($food, 'getServingSize')) {
            return $food->getServingSize() ?? 'Good for 1-2 persons';
        }
        if (method_exists($food, 'getServes')) {
            $serves = $food->getServes();
            return $serves ? "Good for {$serves} persons" : 'Good for 1-2 persons';
        }
        
        return 'Good for 1-2 persons';
    }
}