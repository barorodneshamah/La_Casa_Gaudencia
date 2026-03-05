<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthCheckController extends AbstractController
{
    #[Route('/check-auth', name: 'app_check_auth')]
    public function checkAuth(): JsonResponse
    {
        return new JsonResponse([
            'authenticated' => $this->getUser() !== null
        ]);
    }
}