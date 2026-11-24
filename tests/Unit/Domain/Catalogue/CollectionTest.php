<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Catalogue;

use App\Domain\Catalogue\Collection;
use App\Domain\Shared\Exception\EmptyText;
use App\Domain\Shared\Exception\TextTooLong;
use App\Domain\Shared\Locale;
use App\Domain\Shared\Translation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class CollectionTest extends TestCase
{
    public function testACollectionArrivesHiddenWithItsTrimmedNameAndNoLabel(): void
    {
        $source = new Ulid();

        $collection = Collection::pushed($source, ' Forest folk ', 2);

        self::assertSame([true, 'Forest folk', '', '', false, 2], [$collection->sourceId()->equals($source), $collection->name(), $collection->label()->en, $collection->label()->fr, $collection->isShown(), $collection->position()]);
    }

    public function testTheLabelFallsBackOnTheOtherLanguageThenOnTheName(): void
    {
        $collection = Collection::pushed(new Ulid(), 'Forest folk', 0);
        self::assertSame(['Forest folk', 'Forest folk'], [$collection->labelIn(Locale::En), $collection->labelIn(Locale::Fr)]);

        $collection->relabel(Translation::of('', ' Peuple des bois '));
        self::assertSame(['Peuple des bois', 'Peuple des bois'], [$collection->labelIn(Locale::En), $collection->labelIn(Locale::Fr)]);

        $collection->relabel(Translation::of('Woodland folk', 'Peuple des bois'));
        self::assertSame(['Woodland folk', 'Peuple des bois'], [$collection->labelIn(Locale::En), $collection->labelIn(Locale::Fr)]);
    }

    public function testALabelOfSixtyCharactersIsAcceptedAndOneMoreIsRefused(): void
    {
        $collection = Collection::pushed(new Ulid(), 'Forest folk', 0);
        $collection->relabel(Translation::of(str_repeat('é', Collection::MAX_LABEL), ''));

        $this->expectException(TextTooLong::class);

        $collection->relabel(Translation::of('', str_repeat('é', Collection::MAX_LABEL + 1)));
    }

    public function testANameHolds255CharactersAndCannotBeBlank(): void
    {
        $collection = Collection::pushed(new Ulid(), str_repeat('n', Collection::MAX_NAME), 0);
        self::assertSame(255, mb_strlen($collection->name()));

        try {
            Collection::pushed(new Ulid(), str_repeat('n', Collection::MAX_NAME + 1), 0);
            self::fail('A name of 256 characters went through.');
        } catch (TextTooLong) {
        }

        $this->expectException(EmptyText::class);

        $collection->rename(' ');
    }

    public function testACollectionIsRenamedAndKeepsItsWebsiteSide(): void
    {
        $collection = Collection::pushed(new Ulid(), 'Forest folk', 0);
        $collection->relabel(Translation::of('Woodland folk', ''));
        $collection->show();

        $collection->rename(' Forest people ');

        self::assertSame(['Forest people', 'Woodland folk', true], [$collection->name(), $collection->label()->en, $collection->isShown()]);
    }

    public function testACollectionIsShownAndHidden(): void
    {
        $collection = Collection::pushed(new Ulid(), 'Forest folk', 0);

        $collection->show();
        self::assertTrue($collection->isShown());
        $collection->hide();
        self::assertFalse($collection->isShown());
    }
}
