<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FakeSumUp implements HttpClientInterface
{
    use DecoratorTrait;

    public const string PENDING = 'PENDING';
    public const string PAID = 'PAID';
    public const string FAILED = 'FAILED';
    public const string PAGE = '/_fake/sumup/';
    private const string HOST = 'api.sumup.com';
    private const string CHECKOUTS = '/v0.1/checkouts';
    private const string AUTHORIZATION = 'Authorization: Bearer test-sumup-api-key';
    private const string DOWN = 'down';

    public function __construct(HttpClientInterface $client, private readonly string $directory)
    {
        $this->client = $client;
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (self::HOST !== parse_url($url, \PHP_URL_HOST)) {
            return $this->client->request($method, $url, $options);
        }

        return new MockHttpClient($this->answer(...))->request($method, $url, $options);
    }

    /** @return array<mixed>|null */
    public function checkout(string $id): ?array
    {
        $file = $this->file($id);
        if (null === $file || !is_file($file)) {
            return null;
        }
        $checkout = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($checkout)) {
            return null;
        }

        /* @var array<string, mixed> $checkout */
        return $checkout;
    }

    public function settle(string $id, string $status): void
    {
        $checkout = $this->checkout($id) ?? throw new \LogicException('No checkout '.$id);
        $transaction = self::PAID === $status
            ? ['transaction_code' => 'TFAKE'.strtoupper(bin2hex(random_bytes(4))), 'status' => 'SUCCESSFUL']
            : ['transaction_code' => 'TDECLINED'.strtoupper(bin2hex(random_bytes(4))), 'status' => 'FAILED'];
        $transactions = \is_array($checkout['transactions'] ?? null) ? $checkout['transactions'] : [];
        $this->store([...$checkout, 'status' => $status, 'transactions' => [...$transactions, $transaction]]);
    }

    public function goDown(): void
    {
        new Filesystem()->dumpFile($this->directory.'/'.self::DOWN, '');
    }

    public function forget(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function count(): int
    {
        return \count(glob($this->directory.'/*.json') ?: []);
    }

    /** @param array<string, mixed> $options */
    private function answer(string $method, string $url, array $options): ResponseInterface
    {
        $headers = $options['normalized_headers'] ?? [];
        if (!\is_array($headers) || [self::AUTHORIZATION] !== ($headers['authorization'] ?? null)) {
            return new JsonMockResponse(['error_code' => 'UNAUTHORIZED', 'message' => 'SumUp says the key is wrong'], ['http_code' => 401]);
        }
        if (is_file($this->directory.'/'.self::DOWN)) {
            return new JsonMockResponse(['message' => 'SumUp says it is down'], ['http_code' => 503]);
        }

        $path = (string) parse_url($url, \PHP_URL_PATH);
        if ('POST' === $method && self::CHECKOUTS === $path) {
            return $this->open($options);
        }
        if ('GET' === $method && 1 === preg_match('{^'.self::CHECKOUTS.'/([^/]+)$}', $path, $match)) {
            $checkout = $this->checkout(rawurldecode($match[1]));

            return null === $checkout ? new JsonMockResponse(['error_code' => 'NOT_FOUND', 'message' => 'SumUp says no such checkout'], ['http_code' => 404]) : new JsonMockResponse($checkout);
        }

        return new JsonMockResponse(['error_code' => 'NOT_FOUND'], ['http_code' => 404]);
    }

    /** @param array<string, mixed> $options */
    private function open(array $options): ResponseInterface
    {
        $body = json_decode(\is_string($options['body'] ?? null) ? $options['body'] : '', true);
        if (!\is_array($body)) {
            return new JsonMockResponse(['error_code' => 'INVALID'], ['http_code' => 400]);
        }
        $id = 'fake-'.bin2hex(random_bytes(8));
        $checkout = [...$body, 'id' => $id, 'status' => self::PENDING, 'transactions' => []];
        $this->store($checkout);

        return new JsonMockResponse([...$checkout, 'hosted_checkout_url' => self::PAGE.$id], ['http_code' => 201]);
    }

    /** @param array<mixed> $checkout */
    private function store(array $checkout): void
    {
        $file = $this->file(\is_string($checkout['id'] ?? null) ? $checkout['id'] : '') ?? throw new \LogicException('A checkout needs an id');
        new Filesystem()->dumpFile($file, json_encode($checkout, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION));
    }

    private function file(string $id): ?string
    {
        return 1 === preg_match('/^fake-[0-9a-f]{16}$/', $id) ? $this->directory.'/'.$id.'.json' : null;
    }
}
