<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ProblemResponse extends JsonResponse
{
    /** @param array<string, mixed> $extensions */
    public function __construct(string $title, int $status, string $detail, ?string $code = null, array $extensions = [])
    {
        parent::__construct(['title' => $title, 'status' => $status, 'detail' => $detail, ...(null === $code ? [] : ['code' => $code]), ...$extensions], $status, ['Content-Type' => 'application/problem+json']);
    }
}
