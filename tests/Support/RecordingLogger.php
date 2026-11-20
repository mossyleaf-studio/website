<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Psr\Log\AbstractLogger;

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{mixed, string, array<mixed>}> */
    public array $logs = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->logs[] = [$level, (string) $message, $context];
    }
}
