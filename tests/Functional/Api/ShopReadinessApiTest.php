<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Application\Catalogue\CatalogueQueries;
use App\Tests\Functional\Content\OpensTheShop;
use App\Tests\Functional\Sales\ShopsOnline;
use App\Tests\Support\FakeSumUp;
use App\Tests\Support\Json;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ShopReadinessApiTest extends WebTestCase
{
    use OpensTheShop;
    use ShopsOnline;

    private const string URL = '/api/admin/shop/readiness';
    private const array KEYS = ['identity', 'payment', 'mail', 'shipping', 'products', 'terms', 'legalNotice', 'privacy', 'shippingReturns', 'photos', 'mossytrunk', 'testOrders'];
    private const array SETTINGS = ['MAILER_DSN', 'STUDIO_EMAIL', 'MAILER_FROM', 'SUMUP_API_KEY', 'SUMUP_MERCHANT_CODE', 'MOSSYTRUNK_TOKEN'];

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::SETTINGS as $name) {
            $this->saved[$name] = $_ENV[$name] ?? null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            if (\is_string($value)) {
                $_ENV[$name] = $_SERVER[$name] = $value;
            }
        }
        self::forgetCheckouts();
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testAFreshSiteListsWhatTheShopStillLacksInOrder(): void
    {
        $client = self::signedInClient();

        $readiness = self::readiness($client);

        self::assertFalse($readiness['ready']);
        self::assertSame(self::KEYS, array_keys(self::states($readiness)));
        self::assertSame(['key', 'state', 'count'], array_keys(Json::array($readiness, 'items', 0)));
        self::assertSame([
            'identity' => 'ok',
            'payment' => 'ok',
            'mail' => 'blocker',
            'shipping' => 'blocker',
            'products' => 'blocker',
            'terms' => 'blocker',
            'legalNotice' => 'blocker',
            'privacy' => 'blocker',
            'shippingReturns' => 'warning',
            'photos' => 'ok',
            'mossytrunk' => 'ok',
            'testOrders' => 'ok',
        ], self::states($readiness));
    }

    public function testAShopWithEverythingItNeedsIsReadyEvenWithWarnings(): void
    {
        $client = self::stockedClient();
        self::readyTheShop($client);

        $readiness = self::withARealMailer(static fn (): array => self::readiness($client));

        self::assertTrue($readiness['ready']);
        self::assertSame(['ok', 'ok', 'ok', 'ok', 'ok', 'ok', 'ok', 'ok', 'warning', 'warning', 'ok', 'ok'], array_values(self::states($readiness)));
        self::assertSame(3, self::item($readiness, 'photos')['count']);
        self::assertNull(self::item($readiness, 'mail')['count']);
    }

    public function testEveryLegalPageAndThePhotosTurnOkOnceWritten(): void
    {
        $client = self::stockedClient();
        self::readyTheShop($client);
        $client->jsonRequest('PUT', '/api/admin/legal-pages/shipping-returns', ['text' => '', 'textFr' => '## Retours']);
        foreach (['prints-fox', 'stickers-moth'] as $slug) {
            self::photo($client, self::idOf($slug));
        }

        $readiness = self::readiness($client);

        self::assertSame('ok', self::item($readiness, 'shippingReturns')['state']);
        self::assertSame(['warning', 1], array_values(array_slice(self::item($readiness, 'photos'), 1)));
    }

    public function testALegalPageWrittenInFrenchOnlyIsEnough(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/legal-pages/privacy', ['text' => '', 'textFr' => '## Vie privée']);

        self::assertSame('ok', self::item(self::readiness($client), 'privacy')['state']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function mailSettings(): iterable
    {
        yield 'no transport' => ['MAILER_DSN', ''];
        yield 'the null transport' => ['MAILER_DSN', 'null://null'];
        yield 'no studio inbox' => ['STUDIO_EMAIL', ''];
        yield 'no sender' => ['MAILER_FROM', ''];
    }

    #[DataProvider('mailSettings')]
    public function testMailIsABlockerWithoutARealTransportOrBothAddresses(string $name, string $value): void
    {
        $client = self::signedInClient();

        $readiness = self::withARealMailer(static function () use ($client, $name, $value): array {
            $_ENV[$name] = $_SERVER[$name] = $value;

            return self::readiness($client);
        });

        self::assertSame('blocker', self::item($readiness, 'mail')['state']);
    }

    public function testAnUnsetMailerIsABlocker(): void
    {
        unset($_ENV['MAILER_DSN'], $_SERVER['MAILER_DSN']);
        putenv('MAILER_DSN');

        self::assertSame('blocker', self::item(self::readiness(self::signedInClient()), 'mail')['state']);
    }

    public function testPaymentIsABlockerWithoutTheSumUpSettings(): void
    {
        foreach (['SUMUP_API_KEY', 'SUMUP_MERCHANT_CODE'] as $name) {
            $_ENV[$name] = $_SERVER[$name] = '';

            self::assertSame('blocker', self::item(self::readiness(self::signedInClient()), 'payment')['state'], $name);
            $_ENV[$name] = $_SERVER[$name] = Json::string($this->saved, $name);
            self::ensureKernelShutdown();
        }
    }

    public function testAnEmptyStudioNameIsABlocker(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Critters', 'metaDescription' => 'Drawings']);
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement("UPDATE site_text SET studio_name = ''");

        self::assertSame('blocker', self::item(self::readiness($client), 'identity')['state']);
    }

    public function testAShortMossytrunkTokenIsAWarning(): void
    {
        foreach (['', str_repeat('a', 31)] as $token) {
            $_ENV['MOSSYTRUNK_TOKEN'] = $_SERVER['MOSSYTRUNK_TOKEN'] = $token;

            self::assertSame('warning', self::item(self::readiness(self::signedInClient()), 'mossytrunk')['state'], $token);
            self::ensureKernelShutdown();
        }
        $_ENV['MOSSYTRUNK_TOKEN'] = $_SERVER['MOSSYTRUNK_TOKEN'] = str_repeat('a', 32);

        self::assertSame('ok', self::item(self::readiness(self::signedInClient()), 'mossytrunk')['state']);
    }

    public function testHiddenRetiredOrFreeProductsAreNotOnSale(): void
    {
        $client = self::signedInClient();
        $ids = self::push([self::product(31, 'Prints', 'Fox', 1500), self::product(32, 'Prints', 'Owl', 0)]);
        self::show($client, $ids['prints-owl']);

        self::assertSame(['blocker', 0], array_values(array_slice(self::item(self::readiness($client), 'products'), 1)));
        self::assertSame('ok', self::item(self::readiness($client), 'photos')['state']);
    }

    public function testShippingIsABlockerUntilAZoneIsOn(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/shipping-zones/world', ['price' => 1800, 'freeFrom' => null, 'enabled' => true]);

        self::assertSame('ok', self::item(self::readiness($client), 'shipping')['state']);
    }

    public function testTestOrdersThatStillHoldUnitsAreAWarning(): void
    {
        $client = self::stockedClient();
        $pending = self::placeThroughTheShop($client);
        $paid = self::placeThroughTheShop($client, ['lines' => [['sku' => 'PRI-FOX-A4', 'quantity' => 1]], 'expectedTotal' => 2150]);
        self::sumUp()->settle($paid['paymentId'], FakeSumUp::PAID);
        $client->jsonRequest('GET', '/beta/api/shop/fr/orders/'.$paid['token']);
        self::assertSame('paid', Json::string(self::answer($client), 'status'));

        self::assertSame(['warning', 2], array_values(array_slice(self::item(self::readiness($client), 'testOrders'), 1)));

        $client->jsonRequest('DELETE', '/api/admin/orders/'.self::order($paid['token'])->id()->toString());
        self::assertResponseIsSuccessful();
        self::assertSame(['warning', 1], array_values(array_slice(self::item(self::readiness($client), 'testOrders'), 1)));
    }

    public function testTheReadinessNeedsAnAdmin(): void
    {
        $client = self::createClient();

        $client->jsonRequest('GET', self::URL);

        self::assertResponseStatusCodeSame(401);
    }

    /** @return array<mixed> */
    private static function readiness(KernelBrowser $client): array
    {
        $client->jsonRequest('GET', self::URL);
        self::assertResponseIsSuccessful();
        $readiness = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['ready', 'items'], array_keys($readiness));

        return $readiness;
    }

    /**
     * @param array<mixed> $readiness
     *
     * @return array<string, string>
     */
    private static function states(array $readiness): array
    {
        $states = [];
        foreach (Json::array($readiness, 'items') as $item) {
            $states[Json::string($item, 'key')] = Json::string($item, 'state');
        }

        return $states;
    }

    /**
     * @param array<mixed> $readiness
     *
     * @return array{key: string, state: string, count: int|null}
     */
    private static function item(array $readiness, string $key): array
    {
        $item = array_find(Json::array($readiness, 'items'), static fn (mixed $item): bool => $key === Json::string($item, 'key'));
        $count = Json::at($item, 'count');
        self::assertTrue(null === $count || \is_int($count));

        return ['key' => $key, 'state' => Json::string($item, 'state'), 'count' => $count];
    }

    private static function idOf(string $slug): string
    {
        foreach (self::getContainer()->get(CatalogueQueries::class)->products() as $product) {
            if ($product->slug === $slug) {
                return $product->id;
            }
        }

        throw new \LogicException('No product '.$slug);
    }
}
