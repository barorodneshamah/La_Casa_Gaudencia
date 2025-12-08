<?php

namespace App\Controller;

use App\Repository\RoomRepository;
use App\Repository\TourRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminServicesController extends AbstractController
{
    #[Route('/admin/services', name: 'app_admin_services')]
    public function index(RoomRepository $roomRepository, TourRepository $tourRepository): Response
    {
        $rooms = $roomRepository->findAll();
        $tours = $tourRepository->findAll();

        return $this->render('admin_services/index.html.twig', [
            'rooms' => $rooms,
            'tours' => $tours,
        ]);
    }
}