<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercurePublisher
{
    public function __construct(private HubInterface $hub) {}

    public function publish(string $topic, array $data): void
    {
        try {
            $this->hub->publish(new Update($topic, json_encode($data)));
        } catch (\Throwable) {
            // Silently skip if Mercure hub is not configured or unreachable
        }
    }
}
