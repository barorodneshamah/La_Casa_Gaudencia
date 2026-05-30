<?php
namespace App\Service;

use App\Repository\UserRepository;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class NotificationService
{
    private ?\Kreait\Firebase\Contract\Messaging $messaging = null;

    public function __construct(string $firebaseCredentialsPath)
    {
        // Railway and other cloud hosts cannot persist files — check env var first.
        $credentialsJson = $_ENV['FIREBASE_CREDENTIALS_JSON'] ?? getenv('FIREBASE_CREDENTIALS_JSON') ?: null;

        try {
            if ($credentialsJson) {
                $decoded = json_decode($credentialsJson, true);
                if (is_array($decoded)) {
                    $this->messaging = (new Factory)->withServiceAccount($decoded)->createMessaging();
                    return;
                }
            }

            if (file_exists($firebaseCredentialsPath)) {
                $this->messaging = (new Factory)->withServiceAccount($firebaseCredentialsPath)->createMessaging();
            }
        } catch (\Throwable) {
        }
    }

    public function sendToDevice(string $fcmToken, string $title, string $body, array $data = [], ?string $collapseKey = null): void
    {
        if ($this->messaging === null) {
            return;
        }

        $config = [
            'token'        => $fcmToken,
            'notification' => ['title' => $title, 'body' => $body],
            'data'         => array_map('strval', $data),
        ];

        if ($collapseKey !== null) {
            $config['android'] = ['collapse_key' => $collapseKey];
            $config['apns']    = ['headers' => ['apns-collapse-id' => $collapseKey]];
        }

        $this->messaging->send(CloudMessage::fromArray($config));
    }

    /**
     * Broadcast a notification to every customer that has an FCM token.
     * Silently skips users without a token and absorbs per-device errors.
     */
    public function broadcastToCustomers(UserRepository $users, string $title, string $body, array $data = []): void
    {
        if ($this->messaging === null) {
            return;
        }

        foreach ($users->findAll() as $user) {
            $token = $user->getFcmToken();
            if (!$token) {
                continue;
            }
            try {
                $this->sendToDevice($token, $title, $body, $data);
            } catch (\Throwable) {
                // one bad token must not abort the whole broadcast
            }
        }
    }
}