<?php

declare(strict_types=1);

namespace App\Domain\Content\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidLinkUrl extends DomainException
{
    public function __construct(string $url)
    {
        parent::__construct('content.invalid_link_url', ['url' => $url]);
    }
}
