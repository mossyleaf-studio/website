<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation;

use App\Application\AtomicChange;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use App\Application\Sales\OrderMails;
use App\Domain\Catalogue\ProductRepository;
use App\Domain\Sales\Exception\PaymentUnavailable;
use App\Domain\Sales\OrderRepository;
use App\Presentation\VerifyPayment;
use App\Tests\Support\InMemoryPaymentGateway;
use App\Tests\Support\RecordingLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class VerifyPaymentTest extends TestCase
{
    /** @return iterable<string, array{\Throwable}> */
    public static function failures(): iterable
    {
        yield 'the gateway is down' => [new PaymentUnavailable()];
        yield 'the database fails' => [new \RuntimeException('SQLSTATE[55P03]: lock timeout on order chk-secret')];
    }

    #[DataProvider('failures')]
    public function testAFailedVerificationIsLoggedWithoutDetailsAndNeverEscapes(\Throwable $failure): void
    {
        $orders = $this->createStub(OrderRepository::class);
        $orders->method('withPaymentId')->willThrowException($failure);
        $logger = new RecordingLogger();
        $handler = new ConfirmPaymentHandler(new InMemoryPaymentGateway(), $orders, $this->createStub(ProductRepository::class), $this->createStub(AtomicChange::class), $this->createStub(OrderMails::class), new MockClock(), new RecordingLogger());

        new VerifyPayment($handler, $logger)->verify('chk-secret');

        self::assertSame([['warning', 'A payment could not be verified.', ['failure' => $failure::class]]], $logger->logs);
    }
}
