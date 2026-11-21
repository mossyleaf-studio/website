<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Catalogue;

use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\Exception\InvalidSlug;
use App\Domain\Catalogue\Exception\NegativeAmount;
use App\Domain\Catalogue\Exception\ProductRetired;
use App\Domain\Catalogue\PhotoFile;
use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductPhoto;
use App\Domain\Catalogue\ProductVariant;
use App\Domain\Shared\Exception\EmptyText;
use App\Domain\Shared\Exception\NotFound;
use App\Domain\Shared\Exception\OrderMismatch;
use App\Domain\Shared\Exception\TextTooLong;
use App\Domain\Shared\Locale;
use App\Domain\Shared\Translation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ProductTest extends TestCase
{
    public function testAPushedProductArrivesHiddenWithItsMossytrunkFields(): void
    {
        $sourceId = new Ulid();
        $prints = Category::named(' Prints ', 0);

        $product = Product::pushed($sourceId, ' PRT-FOX ', ' Fox ', ' Prints Fox ', $prints, 1500, 'prints-fox', 4);

        self::assertTrue($sourceId->equals($product->sourceId()));
        self::assertSame(['PRT-FOX', 'Fox', 'Prints Fox', 1500, 'prints-fox', 4], [$product->reference(), $product->name(), $product->displayName(), $product->price(), $product->slug(), $product->position()]);
        self::assertSame($prints, $product->category());
        self::assertFalse($product->isVisible());
        self::assertFalse($product->isRetired());
        self::assertSame(['', ''], [$product->title()->in(Locale::En), $product->description()->in(Locale::Fr)]);
        self::assertSame([], $product->variants());
    }

    public function testAPushOverwritesMossytrunkFieldsAndKeepsTheWebsiteOnes(): void
    {
        $product = self::product();
        $product->describe(Translation::of('Fox print', 'Affiche renard'), Translation::of('A fox among ferns.', ''));
        $product->show();
        $product->moveTo(7);
        $stickers = Category::named('Stickers', 1);

        $product->synchronise('STK-FOX', 'Fox sticker', 'Stickers Fox sticker', $stickers, 300);

        self::assertSame(['STK-FOX', 'Fox sticker', 'Stickers Fox sticker', 300], [$product->reference(), $product->name(), $product->displayName(), $product->price()]);
        self::assertSame($stickers, $product->category());
        self::assertSame(['prints-fox', 'Affiche renard', 'A fox among ferns.', 7], [$product->slug(), $product->title()->in(Locale::Fr), $product->description()->in(Locale::Fr), $product->position()]);
        self::assertTrue($product->isVisible());
    }

    public function testARetiredProductLosesItsVariantsAndComesBackWhenPushedAgain(): void
    {
        $product = self::product();
        $product->stock('PRT-FOX-A4', 'A4', 3, 0);
        $first = new \DateTimeImmutable('2026-11-20 10:00');

        $product->retire($first);
        $product->retire(new \DateTimeImmutable('2026-11-21 10:00'));

        self::assertTrue($product->isRetired());
        self::assertSame($first, $product->retiredAt());
        self::assertSame([], $product->variants());

        $product->synchronise('PRT-FOX', 'Fox', 'Prints Fox', $product->category(), 1500);

        self::assertFalse($product->isRetired());
    }

    public function testVariantsAreRewrittenBySkuInThePushedOrder(): void
    {
        $product = self::product();
        $product->stock('PRT-FOX-A4', 'A4', 3, 0);
        $product->stock('PRT-FOX-A5', 'A5', 8, 1);
        $kept = $product->variants()[1];

        $product->keepVariants(['PRT-FOX-A5', 'PRT-FOX-A3']);
        $product->stock(' PRT-FOX-A3 ', ' A3 ', 1, 0);
        $product->stock('PRT-FOX-A5', 'A5 (148 × 210)', 0, 1);

        self::assertSame(
            [['PRT-FOX-A3', 'A3', 1], ['PRT-FOX-A5', 'A5 (148 × 210)', 0]],
            array_map(static fn (ProductVariant $variant): array => [$variant->sku(), $variant->name(), $variant->stock()], $product->variants()),
        );
        self::assertSame($kept, $product->variants()[1]);
    }

    public function testAProductWithoutVariantsKeepsOneNamelessVariant(): void
    {
        $product = self::product();

        $product->stock('PRT-FOX', ' ', 12, 0);

        self::assertNull($product->variants()[0]->name());
    }

    public function testANegativePriceIsRefused(): void
    {
        $this->expectException(NegativeAmount::class);

        Product::pushed(new Ulid(), 'PRT-FOX', 'Fox', 'Prints Fox', Category::named('Prints', 0), -1, 'prints-fox', 0);
    }

    public function testANegativeStockIsRefused(): void
    {
        $this->expectException(NegativeAmount::class);

        self::product()->stock('PRT-FOX', null, -1, 0);
    }

    public function testAReferenceLongerThanMossytrunkAllowsIsRefused(): void
    {
        $this->expectException(TextTooLong::class);

        self::product()->synchronise(str_repeat('R', Product::MAX_REFERENCE + 1), 'Fox', 'Prints Fox', Category::named('Prints', 0), 1500);
    }

    public function testAProductNeedsAName(): void
    {
        $this->expectException(EmptyText::class);

        self::product()->synchronise('PRT-FOX', ' ', 'Prints Fox', Category::named('Prints', 0), 1500);
    }

    public function testATitleLongerThanItsLimitIsRefused(): void
    {
        $this->expectException(TextTooLong::class);

        self::product()->describe(Translation::of('Fox', str_repeat('é', Product::MAX_TITLE + 1)), Translation::none());
    }

    public function testAProductIsShownAndHidden(): void
    {
        $product = self::product();

        $product->show();
        self::assertTrue($product->isVisible());

        $product->hide();
        self::assertFalse($product->isVisible());
    }

    public function testARetiredProductCannotBeShown(): void
    {
        $product = self::product();
        $product->retire(new \DateTimeImmutable('2026-11-20 10:00'));

        $this->expectException(ProductRetired::class);

        $product->show();
    }

    public function testTheSlugIsEdited(): void
    {
        $product = self::product();

        $product->useSlug(' fox-print-2 ');

        self::assertSame('fox-print-2', $product->slug());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSlugs(): iterable
    {
        yield 'empty' => [' '];
        yield 'capitals' => ['Fox-print'];
        yield 'spaces' => ['fox print'];
        yield 'accents' => ['affiche-renard-é'];
        yield 'a leading dash' => ['-fox'];
        yield 'two dashes in a row' => ['fox--print'];
        yield 'longer than its limit' => [str_repeat('a', Product::MAX_SLUG + 1)];
    }

    #[DataProvider('invalidSlugs')]
    public function testAnInvalidSlugIsRefused(string $slug): void
    {
        $this->expectException(InvalidSlug::class);

        self::product()->useSlug($slug);
    }

    public function testADescriptionLongerThanItsLimitIsRefused(): void
    {
        $this->expectException(TextTooLong::class);

        self::product()->describe(Translation::none(), Translation::of(str_repeat('a', Product::MAX_DESCRIPTION + 1), ''));
    }

    public function testPhotosAreAddedAfterTheLastOne(): void
    {
        $product = self::product();
        $first = self::photo($product);
        $second = self::photo($product);
        $product->removePhoto($first->id());

        $third = self::photo($product);

        self::assertSame([$second, $third], $product->photos());
        self::assertSame([1, 2], array_map(static fn (ProductPhoto $photo): int => $photo->position(), $product->photos()));
        self::assertSame(['full.webp', 1600, 1200, 'thumb.webp', 600, 450], [$third->file(), $third->width(), $third->height(), $third->thumbFile(), $third->thumbWidth(), $third->thumbHeight()]);
    }

    public function testPhotosAreReordered(): void
    {
        $product = self::product();
        $first = self::photo($product);
        $second = self::photo($product);

        $product->reorderPhotos([(string) $second->id(), (string) $first->id()]);

        self::assertSame([$second, $first], $product->photos());
    }

    public function testAPhotoOfAnotherProductCannotBeRemoved(): void
    {
        $product = self::product();
        self::photo($product);
        $other = self::photo(self::product());

        $this->expectException(NotFound::class);

        $product->removePhoto($other->id());
    }

    public function testPhotosCannotBeReorderedWithAPhotoOfAnotherProduct(): void
    {
        $product = self::product();
        $photo = self::photo($product);
        $other = self::photo(self::product());

        $this->expectException(OrderMismatch::class);

        $product->reorderPhotos([(string) $photo->id(), (string) $other->id()]);
    }

    public function testACategoryWithoutLabelShowsItsMossytrunkName(): void
    {
        self::assertSame('Prints', Category::named('Prints', 0)->labelIn(Locale::Fr));
    }

    private static function photo(Product $product): ProductPhoto
    {
        return $product->addPhoto(new Ulid(), new PhotoFile('full.webp', 1600, 1200), new PhotoFile('thumb.webp', 600, 450));
    }

    public function testAPushedDescriptionFillsOnlyTheEmptyLanguages(): void
    {
        $product = self::product();
        $product->describe(Translation::of('', 'Affiche renard'), Translation::of('', 'Écrit par le studio.'));

        $product->prefillDescription(Translation::of(' A fox. ', 'Texte Etsy.'));

        self::assertSame(['A fox.', 'Écrit par le studio.', 'Affiche renard'], [$product->description()->en, $product->description()->fr, $product->title()->fr]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function textsOverTheLimit(): iterable
    {
        $first = rtrim(str_repeat('Un renard dort. ', 150));
        yield 'at the last paragraph break' => [$first."\n\n".str_repeat('Une fougère pousse. ', 100), $first];
        yield 'else at the last sentence end, keeping line breaks' => ["Renard\n".str_repeat('Une phrase courte. ', 300), "Renard\n".rtrim(str_repeat('Une phrase courte. ', 210))];
        yield 'a question or an exclamation ends a sentence too' => [str_repeat('Un renard ? ', 300).str_repeat('Oui ! ', 300), rtrim(str_repeat('Un renard ? ', 300).str_repeat('Oui ! ', 66))];
        yield 'else at the last space, never inside a word' => [str_repeat('fougères ', 500), rtrim(str_repeat('fougères ', 444))];
        yield 'else at the limit when the text has no space' => [str_repeat('é', 4500), str_repeat('é', 4000)];
        yield 'a text at the limit is kept whole' => [str_repeat('é', 4000), str_repeat('é', 4000)];
    }

    #[DataProvider('textsOverTheLimit')]
    public function testAPushedDescriptionOverTheLimitIsCutToFit(string $pushed, string $kept): void
    {
        $product = self::product();

        $product->prefillDescription(Translation::of($pushed, $pushed));

        self::assertSame([$kept, $kept], [$product->description()->en, $product->description()->fr]);
        self::assertLessThanOrEqual(Product::MAX_DESCRIPTION, mb_strlen($product->description()->en));
    }

    private static function product(): Product
    {
        return Product::pushed(new Ulid(), 'PRT-FOX', 'Fox', 'Prints Fox', Category::named('Prints', 0), 1500, 'prints-fox', 0);
    }
}
