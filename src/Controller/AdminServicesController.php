<?php

namespace App\Controller;

use App\Repository\RoomRepository;
use App\Repository\TourRepository;
use App\Repository\FoodRepository;
use App\Repository\PackageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminServicesController extends AbstractController
{
    #[Route('/admin/services', name: 'app_admin_services')]
    public function index(
        RoomRepository $roomRepository, 
        TourRepository $tourRepository,
        FoodRepository $foodRepository, 
        PackageRepository $packageRepository
    ): Response
    {
        $rooms = $roomRepository->findAll();
        $tours = $tourRepository->findAll();
        $foods = $foodRepository->findAll();
        $package = $packageRepository->findAll();

        return $this->render('admin_services/index.html.twig', [
            'rooms' => $rooms,
            'tours' => $tours,
            'foods' => $foods,
            'packages' => $package,
        ]);
    }
}