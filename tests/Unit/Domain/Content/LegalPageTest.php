<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Content;

use App\Domain\Content\LegalKind;
use App\Domain\Content\LegalPage;
use App\Domain\Shared\Exception\TextTooLong;
use App\Domain\Shared\Locale;
use App\Domain\Shared\Translation;
use PHPUnit\Framework\TestCase;

final class LegalPageTest extends TestCase
{
    public function testAPageStartsEmptyAndKeepsWhatIsWritten(): void
    {
        $page = LegalPage::blank(LegalKind::Terms);
        self::assertSame([LegalKind::Terms, '', '', null], [$page->kind(), $page->text()->en, $page->text()->fr, $page->updatedAt()]);

        $now = new \DateTimeImmutable('2027-06-12 10:00');
        $page->write(Translation::of(" ## Terms\n\nPay first. ", ''), $now);

        self::assertSame(["## Terms\n\nPay first.", '', "## Terms\n\nPay first.", $now], [$page->text()->en, $page->text()->fr, $page->text()->in(Locale::Fr), $page->updatedAt()]);
    }

    public function testAPageHoldsThirtyThousandCharactersPerLanguage(): void
    {
        $page = LegalPage::blank(LegalKind::Privacy);
        $page->write(Translation::of(str_repeat('é', LegalPage::MAX_TEXT), str_repeat('a', LegalPage::MAX_TEXT)), new \DateTimeImmutable());

        $this->expectException(TextTooLong::class);
        $page->write(Translation::of('', str_repeat('é', LegalPage::MAX_TEXT + 1)), new \DateTimeImmutable());
    }
}
