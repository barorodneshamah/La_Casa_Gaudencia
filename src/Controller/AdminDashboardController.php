<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TourRepository;
use App\Repository\RoomRepository;
use App\Repository\FoodRepository;
use App\Repository\PackageRepository;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[IsGranted('ROLE_ADMIN')]
final class AdminDashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function index(
        TourRepository $tourRepository,
        RoomRepository $roomRepository,
        FoodRepository $foodRepository,
        PackageRepository $packageRepository,
        PaymentRepository $paymentRepository,
        UserRepository $userRepository,
        ActivityLogRepository $activityLogRepository
    ): Response {
        
        /** @var User $user */
        $user = $this->getUser();
        
        // Total Sales
        $totalSales = $paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        // Today's Sales
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        $todaySales = $paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.createdAt >= :today')
            ->andWhere('p.createdAt < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        // Monthly Sales
        $startOfMonth = new \DateTime('first day of this month midnight');
        $endOfMonth = new \DateTime('first day of next month midnight');
        $monthlySales = $paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.createdAt >= :start')
            ->andWhere('p.createdAt < :end')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Total Transactions
        $totalTransactions = $paymentRepository->count([]);

        // User Statistics
        $totalUsers = $userRepository->count([]);
        
        // Count Staff (users with ROLE_STAFF)
        $totalStaff = $userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_STAFF%')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        // Count Admins
        $totalAdmins = $userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        // Count Guests
        $totalGuests = $userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_GUEST%')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Total Records
        $totalTours = $tourRepository->count([]);
        $totalRooms = $roomRepository->count([]);
        $totalFoods = $foodRepository->count([]);
        $totalPackages = $packageRepository->count([]);
        $totalRecords = $totalTours + $totalRooms + $totalFoods + $totalPackages;

        // Recent Activities (last 10)
        $recentActivities = $activityLogRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            10
        );

        // Today's Activities Count
        $todayActivities = $activityLogRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.createdAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return $this->render('admin_dashboard/index.html.twig', [
            'user' => $user,
            'total_tours' => $totalTours,
            'total_rooms' => $totalRooms,
            'total_foods' => $totalFoods,
            'total_packages' => $totalPackages,
            'total_sales' => $totalSales,
            'today_sales' => $todaySales,
            'monthly_sales' => $monthlySales,
            'total_transactions' => $totalTransactions,
            // New data
            'total_users' => $totalUsers,
            'total_staff' => $totalStaff,
            'total_admins' => $totalAdmins,
            'total_guests' => $totalGuests,
            'total_records' => $totalRecords,
            'recent_activities' => $recentActivities,
            'today_activities' => $todayActivities,
        ]);
    }

    #[Route('/admin/profile/update', name: 'app_admin_profile_update', methods: ['POST'])]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        if (empty($data['fullName']) || empty($data['username']) || empty($data['email'])) {
            return new JsonResponse(['success' => false, 'message' => 'All fields are required'], 400);
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
        }
        
        try {
            $user->setFullName($data['fullName']);
            $user->setUsername($data['username']);
            $user->setEmail($data['email']);
            
            $entityManager->flush();
            
            $nameParts = explode(' ', $data['fullName']);
            $initials = strtoupper(substr($nameParts[0], 0, 1));
            if (count($nameParts) > 1) {
                $initials .= strtoupper(substr(end($nameParts), 0, 1));
            }
            
            return new JsonResponse([
                'success' => true, 
                'message' => 'Profile updated successfully',
                'user' => [
                    'fullName' => $user->getFullName(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'initials' => $initials
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to update profile. Username or email may already exist.'], 500);
        }
    }

    #[Route('/admin/password/change', name: 'app_admin_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';
        
        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            return new JsonResponse(['success' => false, 'message' => 'Current password is incorrect'], 400);
        }
        
        if (strlen($newPassword) < 6) {
            return new JsonResponse(['success' => false, 'message' => 'New password must be at least 6 characters'], 400);
        }
        
        try {
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $entityManager->flush();
            
            return new JsonResponse([
                'success' => true, 
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to change password'], 500);
        }
    }
}