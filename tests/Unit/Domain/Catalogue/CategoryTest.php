<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Catalogue;

use App\Domain\Catalogue\Category;
use App\Domain\Shared\Exception\TextTooLong;
use App\Domain\Shared\Locale;
use App\Domain\Shared\Translation;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testACategoryIsLabelledInBothLanguages(): void
    {
        $category = Category::named('Prints', 0);

        $category->relabel(Translation::of(' Art prints ', ' Affiches '));

        self::assertSame(['Art prints', 'Affiches'], [$category->labelIn(Locale::En), $category->labelIn(Locale::Fr)]);
    }

    public function testALabelOfSixtyCharactersIsAcceptedAndOneMoreIsRefused(): void
    {
        $category = Category::named('Prints', 0);
        $category->relabel(Translation::of(str_repeat('é', Category::MAX_LABEL), ''));

        $this->expectException(TextTooLong::class);

        $category->relabel(Translation::of('', str_repeat('é', Category::MAX_LABEL + 1)));
    }

    public function testACategoryMoves(): void
    {
        $category = Category::named('Prints', 0);

        $category->moveTo(3);

        self::assertSame(3, $category->position());
    }
}
