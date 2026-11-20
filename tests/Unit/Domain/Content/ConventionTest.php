<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Content;

use App\Domain\Content\Convention;
use App\Domain\Content\Exception\ConventionEndsBeforeStart;
use App\Domain\Content\Exception\InvalidLinkUrl;
use App\Domain\Shared\Exception\EmptyText;
use App\Domain\Shared\Exception\TextTooLong;
use App\Domain\Shared\Translation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConventionTest extends TestCase
{
    public function testAConventionKeepsItsDaysPlaceAndNote(): void
    {
        $convention = Convention::plan(' Japan Expo ', ' Paris Nord Villepinte ', new \DateTimeImmutable('2027-07-08 15:30'), new \DateTimeImmutable('2027-07-11'), ' https://www.japan-expo-paris.com/ ', Translation::of('Hall 5', 'Hall 5, stand B12'));

        self::assertSame(
            ['Japan Expo', 'Paris Nord Villepinte', '2027-07-08', '2027-07-11', 'https://www.japan-expo-paris.com/', 'Hall 5, stand B12'],
            [$convention->name(), $convention->place(), $convention->startsOn()->format('Y-m-d'), $convention->endsOn()->format('Y-m-d'), $convention->url(), $convention->note()->fr],
        );
    }

    public function testTheWebsiteIsOptional(): void
    {
        $convention = Convention::plan('Japan Expo', 'Paris', new \DateTimeImmutable('2027-07-08'), new \DateTimeImmutable('2027-07-08'), ' ', Translation::none());
        self::assertNull($convention->url());

        $convention->revise('Japan Expo', 'Paris', new \DateTimeImmutable('2027-07-08'), new \DateTimeImmutable('2027-07-09'), null, Translation::none());
        self::assertSame([null, '2027-07-09'], [$convention->url(), $convention->endsOn()->format('Y-m-d')]);
    }

    public function testAConventionDoesNotEndBeforeItStarts(): void
    {
        $this->expectException(ConventionEndsBeforeStart::class);

        Convention::plan('Japan Expo', 'Paris', new \DateTimeImmutable('2027-07-08'), new \DateTimeImmutable('2027-07-07 23:59'), null, Translation::none());
    }

    #[DataProvider('refusedUrls')]
    public function testTheWebsiteIsAnHttpsAddress(string $url): void
    {
        $this->expectException(InvalidLinkUrl::class);

        Convention::plan('Japan Expo', 'Paris', new \DateTimeImmutable('2027-07-08'), new \DateTimeImmutable('2027-07-08'), $url, Translation::none());
    }

    /** @return iterable<array{string}> */
    public static function refusedUrls(): iterable
    {
        yield ['http://www.japan-expo-paris.com/'];
        yield ['javascript:alert(1)'];
        yield ['https://'.str_repeat('a', Convention::MAX_URL).'.com'];
    }

    /** @param class-string<\Throwable> $exception */
    #[DataProvider('refusedTexts')]
    public function testTheTextsStayWithinTheirLimits(string $name, string $place, Translation $note, string $exception): void
    {
        $this->expectException($exception);

        Convention::plan($name, $place, new \DateTimeImmutable('2027-07-08'), new \DateTimeImmutable('2027-07-08'), null, $note);
    }

    /** @return iterable<string, array{string, string, Translation, class-string<\Throwable>}> */
    public static function refusedTexts(): iterable
    {
        yield 'empty name' => [' ', 'Paris', Translation::none(), EmptyText::class];
        yield 'empty place' => ['Japan Expo', '', Translation::none(), EmptyText::class];
        yield 'long name' => [str_repeat('é', Convention::MAX_NAME + 1), 'Paris', Translation::none(), TextTooLong::class];
        yield 'long place' => ['Japan Expo', str_repeat('é', Convention::MAX_PLACE + 1), Translation::none(), TextTooLong::class];
        yield 'long note' => ['Japan Expo', 'Paris', Translation::of('', str_repeat('é', Convention::MAX_NOTE + 1)), TextTooLong::class];
    }

    public function testAConventionIsOverOnceItsLastDayHasPassed(): void
    {
        $convention = Convention::plan('Japan Expo', 'Paris', new \DateTimeImmutable('2027-07-08'), new \DateTimeImmutable('2027-07-11'), null, Translation::none());

        self::assertSame([false, false, true], [
            $convention->isOverOn(new \DateTimeImmutable('2027-07-08')),
            $convention->isOverOn(new \DateTimeImmutable('2027-07-11 23:59')),
            $convention->isOverOn(new \DateTimeImmutable('2027-07-12')),
        ]);
    }
}
