<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Content\TextBlocks;
use Twig\Attribute\AsTwigFilter;

final readonly class RichBlocksExtension
{
    /** @return list<array<string, mixed>> */
    #[AsTwigFilter('rich_blocks')]
    public function blocks(string $text): array
    {
        return TextBlocks::parse($text);
    }
}
