<?php

declare(strict_types=1);

namespace App\Domain\Content;

enum PublicPage: string
{
    case Note = 'note';
    case Full = 'full';
}
