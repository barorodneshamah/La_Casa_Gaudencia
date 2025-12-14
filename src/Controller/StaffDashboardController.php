<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TourRepository;
use App\Repository\RoomRepository;
use App\Repository\FoodRepository;
use App\Repository\PackageRepository;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[IsGranted('ROLE_STAFF')]
final class StaffDashboardController extends AbstractController
{
    #[Route('/staff/dashboard', name: 'app_staff_dashboard')]
    public function index(
        TourRepository $tourRepository,
        RoomRepository $roomRepository,
        FoodRepository $foodRepository,
        PackageRepository $packageRepository,
        PaymentRepository $paymentRepository
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

        return $this->render('staff_dashboard/index.html.twig', [
            'user' => $user,
            'total_tours' => $tourRepository->count([]),
            'total_rooms' => $roomRepository->count([]),
            'total_foods' => $foodRepository->count([]),
            'total_packages' => $packageRepository->count([]),
            'total_sales' => $totalSales,
            'today_sales' => $todaySales,
            'monthly_sales' => $monthlySales,
            'total_transactions' => $totalTransactions,
        ]);
    }

    #[Route('/staff/profile/update', name: 'app_staff_profile_update', methods: ['POST'])]
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
        
        // Validate required fields
        if (empty($data['fullName']) || empty($data['username']) || empty($data['email'])) {
            return new JsonResponse(['success' => false, 'message' => 'All fields are required'], 400);
        }
        
        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
        }
        
        try {
            $user->setFullName($data['fullName']);
            $user->setUsername($data['username']);
            $user->setEmail($data['email']);
            
            $entityManager->flush();
            
            // Get initials from full name
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

    #[Route('/staff/password/change', name: 'app_staff_change_password', methods: ['POST'])]
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
        
        // Validate current password
        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            return new JsonResponse(['success' => false, 'message' => 'Current password is incorrect'], 400);
        }
        
        // Validate new password length
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