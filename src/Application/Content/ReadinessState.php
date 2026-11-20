<?php

declare(strict_types=1);

namespace App\Application\Content;

enum ReadinessState: string
{
    case Ok = 'ok';
    case Blocker = 'blocker';
    case Warning = 'warning';
}
