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
use PHPUnit\Framework\Attributes\DataProvider;
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
    private const string NBSP = "\u{00A0}";

    public function testTheConfirmationGoesOnceToTheCustomerInFrench(): void
    {
        $order = self::order(Locale::Fr);

        self::mails()->confirm($order);

        self::assertEmailCount(1);
        $email = self::sent();
        self::assertSame(['lou@example.test'], self::addresses($email->getTo()));
        self::assertSame([self::SHOP], self::addresses($email->getFrom()));
        self::assertSame([self::STUDIO], self::addresses($email->getReplyTo()));
        self::assertSame('Votre commande ML-AB12-CD34 sur mossyleaf.studio', $email->getSubject());
        self::assertNull($email->getHtmlBody());
        self::assertEmailTextBodyContains($email, 'ML-AB12-CD34');
        self::assertEmailTextBodyContains($email, '2 × Affiche renard (A4) : 15,00'.self::NBSP.'€ l’unité, 30,00'.self::NBSP.'€');
        self::assertEmailTextBodyContains($email, '1 × Sticker papillon : 3,50'.self::NBSP.'€ l’unité, 3,50'.self::NBSP.'€');
        self::assertEmailTextBodyContains($email, 'Articles : 33,50'.self::NBSP.'€');
        self::assertEmailTextBodyContains($email, 'Livraison : 6,50'.self::NBSP.'€');
        self::assertEmailTextBodyContains($email, 'Total : 40,00'.self::NBSP.'€');
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
        self::assertSame('Your order ML-AB12-CD34 on mossyleaf.studio', $email->getSubject());
        self::assertEmailTextBodyContains($email, '2 × Fox print (A4): €15.00 each, €30.00');
        self::assertEmailTextBodyContains($email, 'Goods: €33.50');
        self::assertEmailTextBodyContains($email, 'Shipping: €6.50');
        self::assertEmailTextBodyContains($email, 'Total: €40.00');
        self::assertEmailTextBodyContains($email, "69001 Lyon\nFrance");
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
        self::assertSame('Nouvelle commande ML-AB12-CD34 : 40,00'.self::NBSP.'€', $email->getSubject());
        self::assertEmailTextBodyContains($email, 'ML-AB12-CD34');
        self::assertEmailTextBodyContains($email, 'Lou Fern <lou@example.test>');
        self::assertEmailTextBodyContains($email, '+33 6 12 34 56 78');
        self::assertEmailTextBodyContains($email, "12 rue des Lilas\nBâtiment B\n69001 Lyon\nFrance");
        self::assertEmailTextBodyContains($email, '2 × Fox print (A4) [PRI-FOX-A4] : 15,00'.self::NBSP.'€ l’unité, 30,00'.self::NBSP.'€');
        self::assertEmailTextBodyNotContains($email, 'Affiche renard');
        self::assertEmailTextBodyContains($email, 'Total : 40,00'.self::NBSP.'€');
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
        self::assertSame('[TEST] Votre commande ML-AB12-CD34 sur mossyleaf.studio', $confirmation->getSubject());
        self::assertSame('[TEST] Nouvelle commande ML-AB12-CD34 : 40,00'.self::NBSP.'€', $notice->getSubject());
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

    /** @return iterable<string, array{string, string}> */
    public static function closedAddresses(): iterable
    {
        yield 'no sender' => ['', self::STUDIO];
        yield 'a blank sender' => ['  ', self::STUDIO];
        yield 'no studio' => [self::SHOP, ''];
    }

    #[DataProvider('closedAddresses')]
    public function testNothingIsSentWithoutBothAddresses(string $from, string $studio): void
    {
        $mails = self::mails(new MailAddresses($from, $studio));

        $mails->confirm(self::order(Locale::Fr));
        $mails->notify(self::order(Locale::Fr));

        self::assertEmailCount(0);
        self::assertFalse(new MailAddresses($from, $studio)->isOpen());
    }

    public function testTheAddressesAreOpenWhenBothAreSet(): void
    {
        self::assertTrue(new MailAddresses(self::SHOP, self::STUDIO)->isOpen());
        self::assertFalse(new MailAddresses(null, self::STUDIO)->isOpen());
        self::assertFalse(new MailAddresses(self::SHOP, null)->isOpen());
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
