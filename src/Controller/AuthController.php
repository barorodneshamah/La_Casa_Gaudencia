<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

#[Route('/api')]
class AuthController extends AbstractController
{
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
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

        // Check if username already exists
        $existing = $em->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existing) {
            return $this->json(['message' => 'Username already taken'], 409);
        }

        // Check if email already exists
        $existingEmail = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingEmail) {
            return $this->json(['message' => 'Email already in use'], 409);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setRoles([User::ROLE_GUEST]); // mobile users are ROLE_GUEST
        $user->setPassword($hasher->hashPassword($user, $password));

        // Generate verification token
        $token = $emailService->generateVerificationToken();
        $user->setVerificationToken($token);
        $user->setIsVerified(false);

        $em->persist($user);
        $em->flush();

        // Generate verification URL
        $verificationUrl = $urlGenerator->generate(
            'app_verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Send verification email
        $emailService->sendVerificationEmail($user, $verificationUrl);

        return $this->json([
            'success' => true,
            'message' => 'Registration successful. Please check your email to verify your account.',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
                'roles' => $user->getRoles()
            ]
        ], 201);
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // allow login with either email or username
        $login    = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        if (!$login || !$password) {
            return $this->json(['message' => 'Missing fields'], 400);
        }

        $repo = $em->getRepository(User::class);
        $user = $repo->findOneBy(['username' => $login]);

        if (!$user || !$hasher->isPasswordValid($user, $password)) {
            return $this->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $jwtManager->create($user);

        return $this->json([
            'token'    => $token,
            'username' => $user->getUsername(),
            'email'    => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'roles'    => $user->getRoles(),
        ]);
    }
}