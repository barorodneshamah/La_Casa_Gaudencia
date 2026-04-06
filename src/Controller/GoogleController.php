<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google')]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        // Redirect to Google OAuth
        return $clientRegistry
            ->getClient('google')
            ->redirect([
                'openid',
                'email',
                'profile'
            ], []);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheck(): never
    {
        // This controller is never executed - it's intercepted by the GoogleAuthenticator
        throw new \LogicException('This should never be reached!');
    }
}