<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminServicesController extends AbstractController
{
    #[Route('/admin/services', name: 'app_admin_services')]
    public function index(): Response
    {
        return $this->render('admin_services/index.html.twig', [
            'controller_name' => 'AdminServicesController',
        ]);
    }
}
