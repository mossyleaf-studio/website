<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/_fake/orders/{reference}/expire', name: 'fake_order_expire', methods: ['POST'], stateless: true)]
final readonly class FakeOrderExpiryController
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(string $reference): Response
    {
        $changed = $this->connection->update(
            'shop_order',
            ['expires_at' => new \DateTimeImmutable('-1 hour')],
            ['reference' => $reference],
            ['expires_at' => Types::DATETIME_IMMUTABLE],
        );

        return 0 === $changed ? throw new NotFoundHttpException() : new Response(status: Response::HTTP_NO_CONTENT);
    }
}
