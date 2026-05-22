<?php
// src/Controller/AuthController.php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client as GoogleClient;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager,
        private LoggerInterface $logger
    ) {}

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EmailVerificationService $emailService,
        UrlGeneratorInterface $urlGenerator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $username = $data['username'] ?? null;
        $email    = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$username || !$email || !$password) {
            return $this->json(['message' => 'Missing fields'], 400);
        }

        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existing) {
            return $this->json(['message' => 'Username already taken'], 409);
        }

        $existingEmail = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingEmail) {
            return $this->json(['message' => 'Email already in use'], 409);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setRoles([User::ROLE_GUEST]);
        $user->setPassword($hasher->hashPassword($user, $password));

        $isMobile = $request->headers->get('X-App-Source') === 'mobile';

        if ($isMobile) {
            // Mobile users are auto-verified — no email link flow
            $user->setIsVerified(true);
        } else {
            $token = $emailService->generateVerificationToken();
            $user->setVerificationToken($token);
            $user->setIsVerified(false);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        if (!$isMobile) {
            try {
                $verificationUrl = $urlGenerator->generate(
                    'app_verify_email',
                    ['token' => $user->getVerificationToken()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
                $emailService->sendVerificationEmail($user, $verificationUrl);
            } catch (\Throwable $e) {
                $this->logger->error('Verification email failed: ' . $e->getMessage());
            }
        }

        return $this->json([
            'success' => true,
            'message' => $isMobile ? 'Registration successful.' : 'Registration successful. Please check your email to verify your account.',
            'user' => [
                'id'         => $user->getId(),
                'username'   => $user->getUsername(),
                'email'      => $user->getEmail(),
                'isVerified' => $user->isVerified(),
                'roles'      => $user->getRoles()
            ]
        ], 201);
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $login    = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        if (!$login || !$password) {
            return $this->json(['message' => 'Missing fields'], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $login]);

        if (!$user || !$hasher->isPasswordValid($user, $password)) {
            return $this->json(['message' => 'Invalid credentials'], 401);
        }

        try {
            $token = $this->jwtManager->create($user);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => 'JWT error: ' . $e->getMessage(),
                'file'    => $e->getFile() . ':' . $e->getLine(),
            ], 500);
        }

        return $this->json([
            'token'    => $token,
            'id'       => $user->getId(),
            'username' => $user->getUsername(),
            'email'    => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'roles'    => $user->getRoles(),
        ]);
    }

    #[Route('/fcm-token', name: 'api_fcm_token', methods: ['POST'])]
    public function saveFcmToken(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $fcmToken = $data['fcmToken'] ?? null;

        if (!$fcmToken) {
            return $this->json(['message' => 'fcmToken is required'], 400);
        }

        $user->setFcmToken($fcmToken);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function googleAuth(
        Request $request,
        #[Autowire('%env(default::GOOGLE_CLIENT_ID)%')] string $googleClientId = ''
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $idToken = $data['idToken'] ?? null;

            if (!$idToken) {
                return $this->json(['message' => 'Missing idToken'], 400);
            }

            // Verify token with Google client library
            $googleClient = new GoogleClient(['client_id' => $googleClientId]);
            $payload = $googleClient->verifyIdToken($idToken);

            if (!$payload || !isset($payload['email'])) {
                $this->logger->error('Google token verification failed');
                return $this->json(['message' => 'Invalid Google token'], 401);
            }

            // Get user data
            $email = $payload['email'];
            $name = $payload['name'] ?? $email;
            $googleId = $payload['sub'] ?? null;

            // Find or create user
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if (!$user) {
                $user = new User();
                $user->setEmail($email);
                $user->setUsername($email);
                $user->setFullName($name);
                $user->setGoogleId($googleId);
                $user->setIsVerified(true);
                $user->setRoles([User::ROLE_GUEST]);
                $user->setPassword('');
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->logger->info('New user created', ['email' => $email]);
            } else {
                if (!$user->getGoogleId()) {
                    $user->setGoogleId($googleId);
                    $this->entityManager->flush();
                }
            }

            // Generate JWT
            $token = $this->jwtManager->create($user);

            return $this->json([
                'success'  => true,
                'token'    => $token,
                'id'       => $user->getId(),
                'username' => $user->getUsername(),
                'email'    => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'roles'    => $user->getRoles(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Google auth error', ['message' => $e->getMessage()]);
            return $this->json(['message' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
}