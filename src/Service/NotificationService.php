<?php
namespace App\Service;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class NotificationService
{
    private ?\Kreait\Firebase\Contract\Messaging $messaging = null;

    public function __construct(string $firebaseCredentialsPath)
    {
        if (!file_exists($firebaseCredentialsPath)) {
            return; // Firebase not configured — notifications will be silently skipped
        }

        try {
            $firebase = (new Factory)->withServiceAccount($firebaseCredentialsPath);
            $this->messaging = $firebase->createMessaging();
        } catch (\Throwable) {
            // Invalid credentials — silently disable notifications
        }
    }

    public function sendToDevice(string $fcmToken, string $title, string $body, array $data = [], ?string $collapseKey = null): void
    {
        if ($this->messaging === null) {
            return;
        }

        $config = [
            'token' => $fcmToken,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => array_map('strval', $data),
        ];

        if ($collapseKey !== null) {
            $config['android'] = ['collapse_key' => $collapseKey];
            $config['apns']    = ['headers' => ['apns-collapse-id' => $collapseKey]];
        }

        $this->messaging->send(CloudMessage::fromArray($config));
    }
}