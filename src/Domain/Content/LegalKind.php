<?php

declare(strict_types=1);

namespace App\Domain\Content;

enum LegalKind: string
{
    case Terms = 'terms';
    case LegalNotice = 'legal-notice';
    case Privacy = 'privacy';
    case ShippingReturns = 'shipping-returns';
}
