<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\ActivityLogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class SecuritySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityLogService $activityLogService,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onLogin',
            LogoutEvent::class => ['onLogout', 0],
        ];
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        
        if ($user instanceof User) {
            $this->activityLogService->logLogin($user);
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        
        if ($token) {
            $user = $token->getUser();
            
            if ($user instanceof User) {
                $this->activityLogService->logLogout($user);
            }
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        $session->clear();
        
        $session->remove('_security.main.target_path');
        
        $session->invalidate(0);

        $response = new RedirectResponse(
            $this->urlGenerator->generate('app_login')
        );
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
        $response->headers->set('Clear-Site-Data', '"cache", "cookies", "storage"');
        
        $response->headers->clearCookie('REMEMBERME');
        
        $response->headers->clearCookie($session->getName());

        $event->setResponse($response);
    }
}