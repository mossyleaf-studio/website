<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Shared\Exception\EmptyText;
use App\Domain\Shared\Exception\TextTooLong;

final readonly class Delivery
{
    public const int MAX_EMAIL = 254;
    public const int MAX_NAME = 120;
    public const int MAX_PHONE = 32;
    public const int MAX_LINE = 120;
    public const int MAX_POSTAL_CODE = 16;
    public const int MAX_CITY = 80;
    public const int MAX_REGION = 80;

    public string $email;
    public string $name;
    public ?string $phone;
    public string $line1;
    public ?string $line2;
    public string $postalCode;
    public string $city;
    public ?string $region;

    public function __construct(string $email, string $name, ?string $phone, string $line1, ?string $line2, string $postalCode, string $city, ?string $region)
    {
        $this->email = self::required('email', $email, self::MAX_EMAIL);
        $this->name = self::required('name', $name, self::MAX_NAME);
        $this->phone = self::optional('phone', $phone, self::MAX_PHONE);
        $this->line1 = self::required('line1', $line1, self::MAX_LINE);
        $this->line2 = self::optional('line2', $line2, self::MAX_LINE);
        $this->postalCode = self::required('postal_code', $postalCode, self::MAX_POSTAL_CODE);
        $this->city = self::required('city', $city, self::MAX_CITY);
        $this->region = self::optional('region', $region, self::MAX_REGION);
    }

    private static function required(string $field, string $value, int $max): string
    {
        return self::optional($field, $value, $max) ?? throw new EmptyText($field);
    }

    private static function optional(string $field, ?string $value, int $max): ?string
    {
        $value = trim($value ?? '');
        if (mb_strlen($value) > $max) {
            throw new TextTooLong($field, $max);
        }

        return '' === $value ? null : $value;
    }
}
