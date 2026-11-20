<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Exception;

use App\Domain\Shared\Exception\DomainException;

final class CatalogueTooLarge extends DomainException
{
    public function __construct(string $subject, int $max)
    {
        parent::__construct('catalogue.too_large', ['subject' => $subject, 'max' => $max]);
    }
}
