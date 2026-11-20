<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail;

use App\Infrastructure\Mail\MailAddresses;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailAddressesTest extends TestCase
{
    /** @return iterable<string, array{?string, ?string}> */
    public static function closed(): iterable
    {
        yield 'no sender' => [null, 'studio@mossyleaf.test'];
        yield 'an empty sender' => ['', 'studio@mossyleaf.test'];
        yield 'a blank sender' => ['  ', 'studio@mossyleaf.test'];
        yield 'no studio' => ['shop@mossyleaf.test', null];
        yield 'a blank studio' => ['shop@mossyleaf.test', " \t"];
        yield 'nothing' => [null, null];
    }

    #[DataProvider('closed')]
    public function testMailStaysHomeUnlessBothAddressesAreSet(?string $from, ?string $studio): void
    {
        self::assertFalse(new MailAddresses($from, $studio)->isOpen());
    }

    public function testBothAddressesOpenTheMailWithoutTheirSpaces(): void
    {
        $addresses = new MailAddresses(' shop@mossyleaf.test ', "studio@mossyleaf.test\n");

        self::assertTrue($addresses->isOpen());
        self::assertSame(['shop@mossyleaf.test', 'studio@mossyleaf.test'], [$addresses->from, $addresses->studio]);
    }

    /** @return iterable<string, array{?string, bool}> */
    public static function transports(): iterable
    {
        yield 'unset' => [null, false];
        yield 'empty' => ['', false];
        yield 'blank' => ['  ', false];
        yield 'the null transport' => ['null://null', false];
        yield 'the null transport in capitals' => [' NULL://default ', false];
        yield 'smtp' => ['smtp://user:secret@smtp.resend.com:587', true];
        yield 'mailpit' => ['smtp://mailpit:1025', true];
    }

    #[DataProvider('transports')]
    public function testOnlyARealTransportCarriesTheMails(?string $dsn, bool $real): void
    {
        self::assertSame($real, new MailAddresses('shop@mossyleaf.test', 'studio@mossyleaf.test', $dsn)->hasTransport());
    }
}
