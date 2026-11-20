<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Api;

use App\Presentation\Api\ProblemResponse;
use App\Tests\Support\Json;
use PHPUnit\Framework\TestCase;

final class ProblemResponseTest extends TestCase
{
    public function testTheCodeIsAddedWhenGiven(): void
    {
        $response = new ProblemResponse('Not possible', 422, 'Sold out.', 'sales.sold_out');

        self::assertSame(['title' => 'Not possible', 'status' => 422, 'detail' => 'Sold out.', 'code' => 'sales.sold_out'], Json::decode((string) $response->getContent()));
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
    }

    public function testTheCodeIsLeftOutWhenNotGiven(): void
    {
        $response = new ProblemResponse('Request refused', 403, 'From another origin.');

        self::assertSame(['title' => 'Request refused', 'status' => 403, 'detail' => 'From another origin.'], Json::decode((string) $response->getContent()));
    }
}
