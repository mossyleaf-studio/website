<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ProblemResponse extends JsonResponse
{
    public function __construct(string $title, int $status, string $detail, ?string $code = null)
    {
        parent::__construct(['title' => $title, 'status' => $status, 'detail' => $detail, ...(null === $code ? [] : ['code' => $code])], $status, ['Content-Type' => 'application/problem+json']);
    }
}
