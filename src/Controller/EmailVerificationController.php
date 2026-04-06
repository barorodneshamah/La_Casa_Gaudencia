<?php

namespace App\Controller;

use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailVerificationController extends AbstractController
{
    #[Route('/verify-email', name: 'app_verify_email')]
    public function verify(
        Request $request,
        EmailVerificationService $service
    ): Response {
        $token = $request->query->get('token');

        if (!$token) {
            return new Response("Missing token");
        }

        $user = $service->verifyToken($token);

        if (!$user) {
            return new Response("Invalid token");
        }

        return new Response("Email verified!");
    }
}