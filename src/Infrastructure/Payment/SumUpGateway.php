<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment;

use App\Application\Sales\CheckoutState;
use App\Application\Sales\OpenedCheckout;
use App\Application\Sales\PaymentGateway;
use App\Application\Sales\PaymentState;
use App\Domain\Sales\Exception\PaymentUnavailable;
use App\Domain\Sales\Order;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SumUpGateway implements PaymentGateway
{
    public const string CHECKOUTS = 'https://api.sumup.com/v0.1/checkouts';
    private const array OPTIONS = ['max_redirects' => 0];
    private const array OPENING = ['timeout' => 10, 'max_duration' => 20];
    private const array FINDING = ['timeout' => 5, 'max_duration' => 8];
    private const array STATES = ['PENDING' => PaymentState::Pending, 'PAID' => PaymentState::Paid, 'FAILED' => PaymentState::Failed, 'EXPIRED' => PaymentState::Expired];
    private const string SUCCESSFUL = 'SUCCESSFUL';
    public const string HOSTED_CHECKOUT = '#^https://([a-z0-9-]+\.)*sumup\.com/#';
    private const int NOT_FOUND = 404;
    private const array UNKNOWN_CHECKOUT = ['status' => 'EXPIRED', 'checkout_reference' => '', 'amount' => 0, 'currency' => CheckoutState::CURRENCY];

    private string $apiKey;
    private string $merchantCode;

    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(default::SUMUP_API_KEY)%')]
        ?string $apiKey,
        #[Autowire('%env(default::SUMUP_MERCHANT_CODE)%')]
        ?string $merchantCode,
        private string $hostedCheckout = self::HOSTED_CHECKOUT,
    ) {
        $this->apiKey = trim((string) $apiKey);
        $this->merchantCode = trim((string) $merchantCode);
    }

    public function isReady(): bool
    {
        return '' !== $this->apiKey && '' !== $this->merchantCode;
    }

    public function open(string $reference, int $amount, string $description, string $webhookUrl, string $redirectUrl, \DateTimeImmutable $validUntil): OpenedCheckout
    {
        $answer = $this->call('POST', self::CHECKOUTS, [...self::OPENING, 'json' => [
            'checkout_reference' => $reference,
            'amount' => $amount / 100,
            'currency' => CheckoutState::CURRENCY,
            'merchant_code' => $this->merchantCode,
            'description' => $description,
            'return_url' => $webhookUrl,
            'redirect_url' => $redirectUrl,
            'valid_until' => $validUntil->format(\DATE_ATOM),
            'hosted_checkout' => ['enabled' => true],
        ]]);

        $id = $answer['id'] ?? null;
        $url = $answer['hosted_checkout_url'] ?? null;
        if (!\is_string($id) || '' === $id || \strlen($id) > Order::MAX_PAYMENT_ID || !\is_string($url) || 1 !== preg_match($this->hostedCheckout, $url)) {
            throw new PaymentUnavailable();
        }

        return new OpenedCheckout($id, $url);
    }

    public function find(string $id): CheckoutState
    {
        $answer = $this->call('GET', self::CHECKOUTS.'/'.rawurlencode($id), self::FINDING, self::UNKNOWN_CHECKOUT);

        $status = $answer['status'] ?? null;
        $state = \is_string($status) ? (self::STATES[$status] ?? null) : null;
        $reference = $answer['checkout_reference'] ?? null;
        $amount = $answer['amount'] ?? null;
        $currency = $answer['currency'] ?? null;
        if (null === $state || !\is_string($reference) || !\is_string($currency) || (!\is_int($amount) && !\is_float($amount))) {
            throw new PaymentUnavailable();
        }

        return new CheckoutState($state, $reference, (int) round($amount * 100), $currency, self::transactionCode($answer['transactions'] ?? null));
    }

    /**
     * @param array<string, mixed>      $options
     * @param array<string, mixed>|null $unknown
     *
     * @return array<mixed>
     */
    private function call(string $method, string $url, array $options, ?array $unknown = null): array
    {
        if (!$this->isReady()) {
            throw new PaymentUnavailable();
        }

        try {
            $response = $this->http->request($method, $url, [...self::OPTIONS, 'auth_bearer' => $this->apiKey, ...$options]);
            if (null !== $unknown && self::NOT_FOUND === $response->getStatusCode()) {
                return $unknown;
            }

            return $response->toArray();
        } catch (ExceptionInterface) {
            throw new PaymentUnavailable();
        }
    }

    private static function transactionCode(mixed $transactions): ?string
    {
        $codes = [];
        foreach (\is_array($transactions) ? $transactions : [] as $transaction) {
            $code = \is_array($transaction) ? ($transaction['transaction_code'] ?? null) : null;
            if (!\is_string($code) || '' === $code || \strlen($code) > Order::MAX_CODE) {
                continue;
            }
            if (self::SUCCESSFUL === ($transaction['status'] ?? null)) {
                return $code;
            }
            $codes[] = $code;
        }

        return array_last($codes);
    }
}
