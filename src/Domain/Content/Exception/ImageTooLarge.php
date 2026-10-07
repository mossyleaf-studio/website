<?php

declare(strict_types=1);

namespace App\Domain\Content\Exception;

use App\Domain\Shared\Exception\DomainException;

final class ImageTooLarge extends DomainException
{
    public const int MAX_MEGABYTES = 20;

    public function __construct(int $maxMegabytes)
    {
        parent::__construct('content.image_too_large', ['max' => $maxMegabytes]);
    }
}
