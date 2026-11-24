<?php

declare(strict_types=1);

namespace App\Tests\Functional\Mail;

use App\Domain\Sales\Country;
use App\Domain\Sales\Delivery;
use App\Domain\Sales\Order;
use App\Domain\Sales\Zone;
use App\Domain\Shared\Locale;
use App\Infrastructure\Mail\MailAddresses;
use App\Infrastructure\Mail\MailerOrderMails;
use App\Tests\Support\FakeOrderUrls;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OrderMailsTest extends KernelTestCase
{
    private const string SHOP = 'shop@mossyleaf.test';
    private const string STUDIO = 'studio@mossyleaf.test';

    public function testTheConfirmationGoesOnceToTheCustomerInFrench(): void
    {
        $order = self::order(Locale::Fr);

        self::mails()->confirm($order);

        self::assertEmailCount(1);
        $email = self::sent();
        self::assertSame(['lou@example.test'], self::addresses($email->getTo()));
        self::assertSame([self::SHOP], self::addresses($email->getFrom()));
        self::assertSame([self::STUDIO], self::addresses($email->getReplyTo()));
        self::assertStringContainsString('ML-AB12-CD34', $email->getSubject() ?? '');
        self::assertNull($email->getHtmlBody());
        self::assertEmailTextBodyContains($email, 'ML-AB12-CD34');
        foreach (['2 × Affiche renard (A4)', '1 × Sticker papillon', '30,00', '3,50', '33,50', '6,50', '40,00'] as $shown) {
            self::assertEmailTextBodyContains($email, $shown);
        }
        self::assertEmailTextBodyContains($email, "Lou Fern\n12 rue des Lilas\nBâtiment B\n69001 Lyon\nFrance");
        self::assertEmailTextBodyContains($email, 'https://shop.test/fr/order/'.$order->token());
        self::assertEmailTextBodyNotContains($email, 'beta');
        self::assertEmailTextBodyNotContains($email, '&');
    }

    public function testTheConfirmationOfAnEnglishOrderIsInEnglish(): void
    {
        $order = self::order(Locale::En);

        self::mails()->confirm($order);

        $email = self::sent();
        self::assertStringContainsString('ML-AB12-CD34', $email->getSubject() ?? '');
        foreach (['2 × Fox print (A4)', '€30.00', '€33.50', '€6.50', '€40.00'] as $shown) {
            self::assertEmailTextBodyContains($email, $shown);
        }
        self::assertEmailTextBodyNotContains($email, 'Affiche renard');
        self::assertEmailTextBodyContains($email, 'https://shop.test/en/order/'.$order->token());
    }

    public function testTheNoticeGoesToTheStudioInFrenchWithTheCustomerAsReplyTo(): void
    {
        $order = self::order(Locale::Fr, phone: '+33 6 12 34 56 78');

        self::mails()->notify($order);

        self::assertEmailCount(1);
        $email = self::sent();
        self::assertSame([self::STUDIO], self::addresses($email->getTo()));
        self::assertSame([self::SHOP], self::addresses($email->getFrom()));
        self::assertSame(['"Lou Fern" <lou@example.test>'], array_map(static fn (Address $address): string => $address->toString(), $email->getReplyTo()));
        self::assertStringContainsString('ML-AB12-CD34', $email->getSubject() ?? '');
        self::assertStringContainsString('40,00', $email->getSubject() ?? '');
        foreach (['ML-AB12-CD34', 'Lou Fern <lou@example.test>', '+33 6 12 34 56 78', "12 rue des Lilas\nBâtiment B\n69001 Lyon", '2 × Fox print (A4) [PRI-FOX-A4]', '30,00', '40,00'] as $shown) {
            self::assertEmailTextBodyContains($email, $shown);
        }
        self::assertEmailTextBodyNotContains($email, 'Affiche renard');
    }

    public function testAWorldOrderShowsItsRegion(): void
    {
        $order = Order::place('ML-AB12-CD34', false, Locale::En, new Delivery('lou@example.test', 'Lou Fern', null, '1 Main Street', null, '10001', 'New York', 'NY'), Country::of('US'), Zone::World, self::lines(), 1800, new \DateTimeImmutable('2026-11-20 10:00:00'));

        self::mails()->confirm($order);

        self::assertEmailTextBodyContains(self::sent(), "1 Main Street\n10001 New York\nNY\nUnited States");
    }

    public function testATestOrderSaysSoInBothSubjectsAndLinksToThePreview(): void
    {
        $order = self::order(Locale::Fr, test: true);

        self::mails()->confirm($order);
        self::mails()->notify($order);

        self::assertEmailCount(2);
        $confirmation = self::getMailerMessage(0);
        $notice = self::getMailerMessage(1);
        self::assertInstanceOf(Email::class, $confirmation);
        self::assertInstanceOf(Email::class, $notice);
        foreach ([$confirmation, $notice] as $email) {
            self::assertStringStartsWith('[TEST] ', $email->getSubject() ?? '');
            self::assertStringContainsString('ML-AB12-CD34', $email->getSubject() ?? '');
        }
        self::assertEmailTextBodyContains($confirmation, 'https://shop.test/beta/fr/order/'.$order->token());
    }

    public function testALineBreakInTheNameOrTheCityAddsNoHeader(): void
    {
        $order = Order::place('ML-AB12-CD34', false, Locale::Fr, new Delivery('lou@example.test', "Lou\r\nBcc: victim@example.test", null, '12 rue des Lilas', null, '69001', "Lyon\r\nBcc: victim@example.test", null), Country::of('FR'), Zone::France, self::lines(), 650, new \DateTimeImmutable('2026-11-20 10:00:00'));

        self::mails()->confirm($order);
        self::mails()->notify($order);

        self::assertEmailCount(2);
        foreach ([0, 1] as $index) {
            $email = self::getMailerMessage($index);
            self::assertInstanceOf(Email::class, $email);
            self::assertFalse($email->getHeaders()->has('Bcc'));
            self::assertStringNotContainsString("\r\nBcc:", $email->getHeaders()->toString());
        }
    }

    public function testAnEmailWithALineBreakIsRefusedAndSendsNothing(): void
    {
        $order = Order::place('ML-AB12-CD34', false, Locale::Fr, new Delivery("lou@example.test\nBcc: victim@example.test", 'Lou Fern', null, '12 rue des Lilas', null, '69001', 'Lyon', null), Country::of('FR'), Zone::France, self::lines(), 650, new \DateTimeImmutable('2026-11-20 10:00:00'));

        foreach (['confirm', 'notify'] as $mail) {
            try {
                self::mails()->{$mail}($order);
                self::fail($mail.' sent a mail to an address with a line break');
            } catch (InvalidArgumentException) {
            }
        }

        self::assertEmailCount(0);
    }

    public function testTheShippedMailGoesToTheCustomerInFrenchWithTheTrackingNumber(): void
    {
        $order = self::shipped(Locale::Fr, 'LA123456789FR');

        self::mails()->shipped($order);

        self::assertEmailCount(1);
        $email = self::sent();
        self::assertSame(['lou@example.test'], self::addresses($email->getTo()));
        self::assertSame([self::SHOP], self::addresses($email->getFrom()));
        self::assertSame([self::STUDIO], self::addresses($email->getReplyTo()));
        self::assertStringContainsString('ML-AB12-CD34', $email->getSubject() ?? '');
        self::assertNull($email->getHtmlBody());
        foreach (['ML-AB12-CD34', 'LA123456789FR', '2 × Affiche renard (A4)', '30,00'] as $shown) {
            self::assertEmailTextBodyContains($email, $shown);
        }
        self::assertEmailTextBodyContains($email, 'https://shop.test/fr/order/'.$order->token());
        self::assertEmailTextBodyNotContains($email, 'TEST');
        self::assertEmailTextBodyNotContains($email, '&');
    }

    public function testTheShippedMailOfAnEnglishOrderWithoutTrackingIsInEnglish(): void
    {
        $order = self::shipped(Locale::En, null);

        self::mails()->shipped($order);

        $email = self::sent();
        self::assertStringContainsString('ML-AB12-CD34', $email->getSubject() ?? '');
        self::assertEmailTextBodyContains($email, '2 × Fox print (A4)');
        self::assertEmailTextBodyContains($email, '€30.00');
        self::assertEmailTextBodyContains($email, 'https://shop.test/en/order/'.$order->token());
        self::assertEmailTextBodyNotContains($email, 'Tracking');
    }

    public function testTheShippedMailOfATestOrderSaysSo(): void
    {
        $order = self::shipped(Locale::Fr, null, test: true);

        self::mails()->shipped($order);

        $email = self::sent();
        self::assertStringStartsWith('[TEST] ', $email->getSubject() ?? '');
        self::assertStringContainsString('ML-AB12-CD34', $email->getSubject() ?? '');
        self::assertEmailTextBodyContains($email, 'TEST');
        self::assertEmailTextBodyContains($email, 'https://shop.test/beta/fr/order/'.$order->token());
    }

    public function testNothingIsSentWithoutBothAddresses(): void
    {
        $mails = self::mails(new MailAddresses('', self::STUDIO));

        $mails->confirm(self::order(Locale::Fr));
        $mails->notify(self::order(Locale::Fr));
        $mails->shipped(self::shipped(Locale::Fr, 'LA1'));

        self::assertEmailCount(0);
    }

    private static function mails(?MailAddresses $addresses = null): MailerOrderMails
    {
        $container = self::getContainer();

        return new MailerOrderMails($container->get(MailerInterface::class), $container->get(TranslatorInterface::class), new FakeOrderUrls(), $addresses ?? new MailAddresses(self::SHOP, self::STUDIO));
    }

    private static function order(Locale $locale, bool $test = false, ?string $region = null, ?string $phone = null): Order
    {
        return Order::place('ML-AB12-CD34', $test, $locale, new Delivery('lou@example.test', 'Lou Fern', $phone, '12 rue des Lilas', 'Bâtiment B', '69001', 'Lyon', $region), Country::of('FR'), Zone::France, self::lines($locale), 650, new \DateTimeImmutable('2026-11-20 10:00:00'));
    }

    private static function shipped(Locale $locale, ?string $trackingNumber, bool $test = false): Order
    {
        $order = self::order($locale, $test);
        $order->pay('TX', new \DateTimeImmutable('2026-11-20 10:05:00'));
        $order->ship($trackingNumber, new \DateTimeImmutable('2026-11-21 09:00:00'));

        return $order;
    }

    /** @return list<array{sku: string, name: string, title: string, variant: ?string, unitPrice: int, quantity: int}> */
    private static function lines(Locale $locale = Locale::Fr): array
    {
        return [
            ['sku' => 'PRI-FOX-A4', 'name' => 'Fox print', 'title' => Locale::Fr === $locale ? 'Affiche renard' : 'Fox print', 'variant' => 'A4', 'unitPrice' => 1500, 'quantity' => 2],
            ['sku' => 'STI-MOTH', 'name' => 'Moth sticker', 'title' => Locale::Fr === $locale ? 'Sticker papillon' : 'Moth sticker', 'variant' => null, 'unitPrice' => 350, 'quantity' => 1],
        ];
    }

    private static function sent(): Email
    {
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }

    /**
     * @param array<Address> $addresses
     *
     * @return list<string>
     */
    private static function addresses(array $addresses): array
    {
        return array_values(array_map(static fn (Address $address): string => $address->getAddress(), $addresses));
    }
}
