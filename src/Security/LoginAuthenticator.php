<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function authenticate(Request $request): Passport
    {
        $username = $request->getPayload()->getString('username');

        $request->getSession()->set(
            SecurityRequestAttributes::LAST_USERNAME,
            $username
        );

        return new Passport(
            new UserBadge($username),
            new PasswordCredentials(
                $request->getPayload()->getString('password')
            ),
            [
                new CsrfTokenBadge(
                    'authenticate',
                    $request->getPayload()->getString('_csrf_token')
                ),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {
        // Clear any stored target path
        $this->removeTargetPath($request->getSession(), $firewallName);

        $user  = $token->getUser();
        $roles = $user->getRoles();

        // Determine redirect URL based on role
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $url = $this->urlGenerator->generate('app_admin_dashboard');
        } elseif (in_array('ROLE_STAFF', $roles, true)) {
            $url = $this->urlGenerator->generate('app_staff_dashboard');
        } else {
            $url = $this->urlGenerator->generate('app_home');
        }

        $response = new RedirectResponse($url);
        
        // Add no-cache headers
        $response->headers->set('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');

        return $response;
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}