<?php

declare(strict_types=1);

namespace App\Tests\Functional\Sales;

use App\Application\Sales\CartLine;
use App\Application\Sales\PlaceOrder\PlaceOrder;
use App\Application\Sales\PlaceOrder\PlaceOrderHandler;
use App\Domain\Sales\Delivery;
use App\Domain\Sales\Zone;
use App\Domain\Shared\Locale;
use App\Tests\Support\FakeSumUp;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpClient\MockHttpClient;

trait ShopsOnline
{
    use PlacesOrders;

    private const array CUSTOMER = ['lou@example.test', 'Lou Martin', '+33 6 12 34 56 78', '1 rue des Fougères', 'Bâtiment B', '75011', 'Paris'];

    private static function forgetCheckouts(): void
    {
        self::sumUp()->forget();
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function form(array $overrides = []): array
    {
        return [
            'lines' => [['sku' => 'STI-MOTH', 'quantity' => 2]],
            'country' => 'FR',
            'expectedTotal' => 1250,
            'email' => self::CUSTOMER[0],
            'name' => self::CUSTOMER[1],
            'phone' => self::CUSTOMER[2],
            'line1' => self::CUSTOMER[3],
            'line2' => self::CUSTOMER[4],
            'postalCode' => self::CUSTOMER[5],
            'city' => self::CUSTOMER[6],
            'region' => null,
            'terms' => true,
            ...$overrides,
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array{reference: string, token: string, paymentId: string, paymentUrl: string}
     */
    private static function placeThroughTheShop(KernelBrowser $client, array $overrides = [], string $locale = 'fr'): array
    {
        $client->jsonRequest('POST', "/beta/api/shop/{$locale}/orders", self::form($overrides), ['REMOTE_ADDR' => '203.0.113.40']);
        self::assertResponseStatusCodeSame(201);
        $placed = Json::decode((string) $client->getResponse()->getContent());
        $token = substr(Json::string($placed, 'statusUrl'), -43);

        return [
            'reference' => Json::string($placed, 'reference'),
            'token' => $token,
            'paymentId' => self::order($token)->paymentId() ?? throw new \LogicException('No checkout opened'),
            'paymentUrl' => Json::string($placed, 'paymentUrl'),
        ];
    }

    /** @return array{reference: string, token: string, paymentId: string} */
    private static function placeWithoutRequest(): array
    {
        self::push(self::shopProducts(3));
        self::zone(Zone::France, 650, null, true);
        $placed = self::getContainer()->get(PlaceOrderHandler::class)(new PlaceOrder([new CartLine('STI-MOTH', 2)], 'FR', new Delivery(...self::CUSTOMER, region: null), Locale::En, true, 1250));

        return ['reference' => $placed->reference, 'token' => $placed->token, 'paymentId' => self::order($placed->token)->paymentId() ?? throw new \LogicException('No checkout opened')];
    }

    private static function stockedClient(): KernelBrowser
    {
        self::stockTheShop();
        $client = self::getClient();
        self::assertInstanceOf(KernelBrowser::class, $client);

        return $client;
    }

    private static function sumUp(): FakeSumUp
    {
        return new FakeSumUp(new MockHttpClient(), self::getContainer()->getParameter('kernel.share_dir').'/fake-sumup');
    }

    /** @return array<mixed> */
    private static function answer(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getResponse()->getContent());
    }
}
