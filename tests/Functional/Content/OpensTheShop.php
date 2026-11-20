<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

trait OpensTheShop
{
    private static function readyTheShop(KernelBrowser $client): void
    {
        $client->jsonRequest('PUT', '/api/admin/shipping-zones/france', ['price' => 650, 'freeFrom' => null, 'enabled' => true]);
        self::assertResponseIsSuccessful();
        foreach (['terms' => 'Terms of sale', 'legal-notice' => 'Legal notice', 'privacy' => 'Privacy'] as $kind => $title) {
            $client->jsonRequest('PUT', '/api/admin/legal-pages/'.$kind, ['text' => "## {$title}\n\nThe studio sells drawings.", 'textFr' => '']);
            self::assertResponseIsSuccessful();
        }
    }

    private static function publish(KernelBrowser $client, string $page): void
    {
        self::withARealMailer(static fn () => $client->jsonRequest('POST', '/api/admin/publication', ['page' => $page]));
        self::assertResponseIsSuccessful();
    }

    private static function openTheShop(KernelBrowser $client): void
    {
        self::readyTheShop($client);
        self::publish($client, 'full');
    }

    /**
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    private static function withARealMailer(callable $call): mixed
    {
        $saved = $_ENV['MAILER_DSN'] ?? null;
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'smtp://mail.mossyleaf.test:25';
        try {
            return $call();
        } finally {
            if (\is_string($saved)) {
                $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = $saved;
            } else {
                unset($_ENV['MAILER_DSN'], $_SERVER['MAILER_DSN']);
            }
        }
    }
}
