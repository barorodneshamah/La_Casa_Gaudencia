<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MercureTokenController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(MERCURE_JWT_SECRET)%')]
        private string $mercureSecret,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')]
        private string $mercurePublicUrl,
    ) {}

    #[Route('/api/mercure/token', name: 'api_mercure_token', methods: ['GET'])]
    public function token(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $subscriberToken = $this->buildJwt(['/topic/reservations', '/topic/payments', '/topic/contact-messages']);

        return $this->json([
            'token'  => $subscriberToken,
            'hubUrl' => $this->mercurePublicUrl,
        ]);
    }

    private function buildJwt(array $subscribeTopics): string
    {
        $header  = $this->b64u(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = $this->b64u(json_encode(['mercure' => ['subscribe' => $subscribeTopics]]));
        $sig     = $this->b64u(hash_hmac('sha256', "{$header}.{$payload}", $this->mercureSecret, true));

        return "{$header}.{$payload}.{$sig}";
    }

    private function b64u(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
