<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Sales\Exception\CheckoutStillOpen;
use App\Domain\Sales\Exception\InvalidTrackingNumber;
use App\Domain\Sales\Exception\NotATestOrder;
use App\Domain\Sales\Exception\OrderMoveRefused;
use App\Domain\Sales\Exception\RegionRequired;
use App\Domain\Shared\Exception\TextTooLong;
use App\Domain\Shared\Locale;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'shop_order')]
class Order
{
    public const int TOKEN_LENGTH = 43;
    public const string HOLD = '+35 minutes';
    public const string PAYMENT_WINDOW = '+30 minutes';
    public const int MAX_PAYMENT_ID = 255;
    public const int MAX_CODE = 64;
    public const string CONTROL_CHARACTERS = '/[\x00-\x1F\x7F\x{2028}\x{2029}]/u';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: OrderReference::LENGTH, unique: true)]
    private string $reference;

    #[ORM\Column(length: self::TOKEN_LENGTH, unique: true)]
    private string $token;

    #[ORM\Column(length: 16, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::Pending;

    #[ORM\Column]
    private bool $test;

    #[ORM\Column(length: 2, enumType: Locale::class)]
    private Locale $locale;

    #[ORM\Column(length: Delivery::MAX_EMAIL)]
    private string $email;

    #[ORM\Column(length: Delivery::MAX_NAME)]
    private string $name;

    #[ORM\Column(length: Delivery::MAX_PHONE, nullable: true)]
    private ?string $phone;

    #[ORM\Column(length: Delivery::MAX_LINE)]
    private string $line1;

    #[ORM\Column(length: Delivery::MAX_LINE, nullable: true)]
    private ?string $line2;

    #[ORM\Column(length: Delivery::MAX_POSTAL_CODE)]
    private string $postalCode;

    #[ORM\Column(length: Delivery::MAX_CITY)]
    private string $city;

    #[ORM\Column(length: Delivery::MAX_REGION, nullable: true)]
    private ?string $region;

    #[ORM\Column(length: 2)]
    private string $country;

    #[ORM\Column(length: 16, enumType: Zone::class)]
    private Zone $zone;

    #[ORM\Column]
    private int $goods;

    #[ORM\Column]
    private int $shipping;

    #[ORM\Column(length: self::MAX_PAYMENT_ID, unique: true, nullable: true)]
    private ?string $paymentId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $paymentUrl = null;

    #[ORM\Column(length: self::MAX_CODE, nullable: true)]
    private ?string $transactionCode = null;

    #[ORM\Column(length: self::MAX_CODE, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $exportedAt = null;

    /** @var Collection<int, OrderLine> */
    #[ORM\OneToMany(targetEntity: OrderLine::class, mappedBy: 'order', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    /**
     * @param list<array{sku: string, name: string, title: string, variant: ?string, unitPrice: int, quantity: int}> $lines
     */
    public static function place(string $reference, bool $test, Locale $locale, Delivery $delivery, Country $country, Zone $zone, array $lines, int $shipping, \DateTimeImmutable $now): self
    {
        if (Zone::World === $zone && null === $delivery->region) {
            throw new RegionRequired();
        }

        $order = new self();
        $order->id = new Ulid();
        $order->reference = $reference;
        $order->token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $order->test = $test;
        $order->locale = $locale;
        $order->email = $delivery->email;
        $order->name = $delivery->name;
        $order->phone = $delivery->phone;
        $order->line1 = $delivery->line1;
        $order->line2 = $delivery->line2;
        $order->postalCode = $delivery->postalCode;
        $order->city = $delivery->city;
        $order->region = $delivery->region;
        $order->country = $country->code;
        $order->zone = $zone;
        $order->shipping = $shipping;
        $order->createdAt = $now;
        $order->expiresAt = $now->modify(self::HOLD);
        $order->lines = new ArrayCollection();
        foreach ($lines as $position => $line) {
            $order->lines->add(new OrderLine($order, $line['sku'], $line['name'], $line['title'], $line['variant'], $line['unitPrice'], $line['quantity'], $position));
        }
        $order->goods = array_sum(array_map(static fn (OrderLine $line): int => $line->total(), $order->lines()));

        return $order;
    }

    public function openPayment(string $id, string $url): void
    {
        $this->paymentId = $id;
        $this->paymentUrl = $url;
    }

    public function pay(?string $transactionCode, \DateTimeImmutable $at): void
    {
        $this->move(OrderStatus::Paid);
        $this->transactionCode = $transactionCode;
        $this->paidAt = $at;
    }

    public function fail(): void
    {
        $this->move(OrderStatus::Failed);
    }

    public function expire(): void
    {
        $this->move(OrderStatus::Expired);
    }

    public function isHeldAt(\DateTimeImmutable $now): bool
    {
        return OrderStatus::Pending === $this->status && $this->expiresAt > $now;
    }

    public function canBePaidAt(\DateTimeImmutable $now): bool
    {
        return OrderStatus::Pending === $this->status && $this->createdAt->modify(self::PAYMENT_WINDOW) > $now;
    }

    private function move(OrderStatus $next): void
    {
        if (!$this->status->allows($next)) {
            throw new OrderMoveRefused($this->status, $next);
        }
        $this->status = $next;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function isTest(): bool
    {
        return $this->test;
    }

    public function locale(): Locale
    {
        return $this->locale;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function line1(): string
    {
        return $this->line1;
    }

    public function line2(): ?string
    {
        return $this->line2;
    }

    public function postalCode(): string
    {
        return $this->postalCode;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function region(): ?string
    {
        return $this->region;
    }

    public function country(): string
    {
        return $this->country;
    }

    public function zone(): Zone
    {
        return $this->zone;
    }

    public function goods(): int
    {
        return $this->goods;
    }

    public function shipping(): int
    {
        return $this->shipping;
    }

    public function total(): int
    {
        return $this->goods + $this->shipping;
    }

    public function paymentId(): ?string
    {
        return $this->paymentId;
    }

    public function paymentUrl(): ?string
    {
        return $this->paymentUrl;
    }

    public function transactionCode(): ?string
    {
        return $this->transactionCode;
    }

    public function trackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function paidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function shippedAt(): ?\DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function cancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function exportedAt(): ?\DateTimeImmutable
    {
        return $this->exportedAt;
    }

    /** @return list<OrderLine> */
    public function lines(): array
    {
        return array_values($this->lines->toArray());
    }

    public function ship(?string $trackingNumber, \DateTimeImmutable $at): void
    {
        $trackingNumber = trim($trackingNumber ?? '');
        if (mb_strlen($trackingNumber) > self::MAX_CODE) {
            throw new TextTooLong('tracking_number', self::MAX_CODE);
        }
        if (1 === preg_match(self::CONTROL_CHARACTERS, $trackingNumber)) {
            throw new InvalidTrackingNumber();
        }
        $this->move(OrderStatus::Shipped);
        $this->trackingNumber = '' === $trackingNumber ? null : $trackingNumber;
        $this->shippedAt = $at;
    }

    public function cancel(\DateTimeImmutable $at): void
    {
        $this->move(OrderStatus::Cancelled);
        $this->cancelledAt = $at;
    }

    public function holdsUnits(): bool
    {
        return OrderStatus::Paid === $this->status || OrderStatus::Shipped === $this->status;
    }

    /** @return array<string, int> */
    public function units(): array
    {
        $units = [];
        foreach ($this->lines() as $line) {
            $units[$line->sku()] = ($units[$line->sku()] ?? 0) + $line->quantity();
        }

        return $units;
    }

    public function assertDeletable(\DateTimeImmutable $now): void
    {
        if (!$this->test) {
            throw new NotATestOrder($this->reference);
        }
        if ($this->canBePaidAt($now)) {
            throw new CheckoutStillOpen($this->reference);
        }
    }

    public function export(\DateTimeImmutable $at): void
    {
        $this->exportedAt ??= $at;
    }
}
