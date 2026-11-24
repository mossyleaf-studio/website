<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Api\Shop;

use App\Presentation\Api\Shop\OrderPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

final class OrderPayloadTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function violations(): iterable
    {
        yield 'no email' => [['email' => ''], 'email'];
        yield 'an email that is not one' => [['email' => 'lou at example'], 'email'];
        yield 'an email on two lines' => [['email' => "lou@example.test\nBcc: x@example.test"], 'email'];
        yield 'an email too long' => [['email' => str_repeat('a', 250).'@example.test'], 'email'];
        yield 'no name' => [['name' => '  '], 'name'];
        yield 'a name too long' => [['name' => str_repeat('a', 121)], 'name'];
        yield 'a name on two lines' => [['name' => "Lou\r\nMartin"], 'name'];
        yield 'a name with a NUL byte' => [['name' => "Ana\u{0}"], 'name'];
        yield 'a city with an escape' => [['city' => "Lyon\u{1b}[2J"], 'city'];
        yield 'a street with a line separator' => [['line1' => "12 rue\u{2028}des Fougères"], 'line1'];
        yield 'a phone with a delete' => [['phone' => "06\u{7f}12"], 'phone'];
        yield 'a phone too long' => [['phone' => str_repeat('1', 33)], 'phone'];
        yield 'no street' => [['line1' => ''], 'line1'];
        yield 'a street too long' => [['line1' => str_repeat('a', 121)], 'line1'];
        yield 'a second line too long' => [['line2' => str_repeat('a', 121)], 'line2'];
        yield 'no postal code' => [['postalCode' => ''], 'postalCode'];
        yield 'a postal code too long' => [['postalCode' => str_repeat('1', 17)], 'postalCode'];
        yield 'no city' => [['city' => ''], 'city'];
        yield 'a city too long' => [['city' => str_repeat('a', 81)], 'city'];
        yield 'a region too long' => [['region' => str_repeat('a', 81)], 'region'];
        yield 'no region for the world' => [['country' => 'US'], 'region'];
        yield 'a blank region for the world' => [['country' => 'JP', 'region' => ' '], 'region'];
        yield 'no country' => [['country' => ''], 'country'];
        yield 'the terms refused' => [['terms' => false], 'terms'];
    }

    /**
     * @param array<string, mixed> $fields
     */
    #[DataProvider('violations')]
    public function testEachViolationIsRefusedByItsPropertyPath(array $fields, string $propertyPath): void
    {
        self::assertSame([$propertyPath], self::paths($fields));
    }

    public function testACompleteOrderIsAccepted(): void
    {
        self::assertSame([], self::paths([]));
        self::assertSame([], self::paths(['country' => 'US', 'region' => 'California']));
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return list<string>
     */
    private static function paths(array $fields): array
    {
        /** @var array{country: string, email: string, name: string, phone: string, line1: string, line2: string, postalCode: string, city: string, region: ?string, terms: bool} $f */
        $f = [...[
            'country' => 'FR',
            'email' => 'lou@example.test',
            'name' => 'Lou Martin',
            'phone' => '+33 6 12 34 56 78',
            'line1' => '1 rue des Fougères',
            'line2' => 'Bâtiment B',
            'postalCode' => '75011',
            'city' => 'Paris',
            'region' => null,
            'terms' => true,
        ], ...$fields];
        $payload = new OrderPayload(
            country: $f['country'],
            email: $f['email'],
            name: $f['name'],
            phone: $f['phone'],
            line1: $f['line1'],
            line2: $f['line2'],
            postalCode: $f['postalCode'],
            city: $f['city'],
            region: $f['region'],
            terms: $f['terms'],
        );
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        return array_values(array_unique(array_map(static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(), iterator_to_array($validator->validate($payload)))));
    }
}
