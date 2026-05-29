<?php
namespace App\Service;

/**
 * Pushes real-time events from Symfony to the Node.js WebSocket server.
 *
 * The WS server runs separately:  node websocket-server/server.js
 * Default broadcast endpoint:     POST http://127.0.0.1:9090/broadcast
 *
 * Usage in a controller or event subscriber:
 *   $this->wsPublisher->pushToUser($userId, 'Booking Confirmed', 'Your room is ready!', 'booking');
 *   $this->wsPublisher->broadcast('Site Update', 'We have a new offer for you!', 'promo');
 */
class WebSocketPublisher
{
    private string $broadcastUrl;

    public function __construct(string $wsServerUrl = '')
    {
        $this->broadcastUrl = $wsServerUrl !== '' ? rtrim($wsServerUrl, '/') . '/broadcast' : '';
    }

    /**
     * Send a notification to a specific user (by username or int id), or to all when null.
     * Pass the username string — the WS server registers sockets by the JWT 'username' claim.
     */
    public function push(
        string          $title,
        string          $body,
        string          $type   = 'general',
        int|string|null $userId = null,
        array           $data   = [],
    ): void {
        $payload = [
            'id'        => 'ws-' . uniqid('', true),
            'title'     => $title,
            'body'      => $body,
            'type'      => $type,
            'createdAt' => (new \DateTimeImmutable())->format('c'),
        ];

        if ($userId !== null) {
            $payload['userId'] = $userId;
        }

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        $this->httpPost($payload);
    }

    /** Convenience: send to one user (pass username string to match JWT identity). */
    public function pushToUser(int|string $userId, string $title, string $body, string $type = 'general', array $data = []): void
    {
        $this->push($title, $body, $type, $userId, $data);
    }

    /** Convenience: send to all connected users. */
    public function broadcast(string $title, string $body, string $type = 'general', array $data = []): void
    {
        $this->push($title, $body, $type, null, $data);
    }

    // ── Internal ────────────────────────────────────────────────────────────────

    private function httpPost(array $payload): void
    {
        if ($this->broadcastUrl === '') {
            return;
        }

        $ch = curl_init($this->broadcastUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 2,        // never block the HTTP response
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);
        curl_exec($ch);   // fire-and-forget; errors are silently ignored
        curl_close($ch);
    }
}
