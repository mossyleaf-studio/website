<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Api\Shop;

use App\Presentation\Api\Shop\ContactPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

final class ContactPayloadTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function refusedFields(): iterable
    {
        yield 'no name' => [['name' => '  '], 'name'];
        yield 'a name too long' => [['name' => str_repeat('a', 101)], 'name'];
        yield 'a name with a line break' => [['name' => "Lou\r\nBcc: victim@example.test"], 'name'];
        yield 'no email' => [['email' => ''], 'email'];
        yield 'an invalid email' => [['email' => 'lou@'], 'email'];
        yield 'an email too long' => [['email' => str_repeat('a', 243).'@example.test'], 'email'];
        yield 'an email with a line break' => [['email' => "lou@example.test\nBcc: victim@example.test"], 'email'];
        yield 'an email ending with a line break' => [['email' => "lou@example.test\n"], 'email'];
        yield 'an email with two dots in a row' => [['email' => 'lou..fern@example.test'], 'email'];
        yield 'an email starting with a dot' => [['email' => '.lou@example.test'], 'email'];
        yield 'an email with a dot before the at' => [['email' => 'lou.@example.test'], 'email'];
        yield 'an email with a local part over 64 characters' => [['email' => str_repeat('a', 65).'@example.test'], 'email'];
        yield 'no message' => [['message' => "\n "], 'message'];
        yield 'a message too long' => [['message' => str_repeat('a', 4001)], 'message'];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('refusedFields')]
    public function testEachFieldViolationIsRefused(array $fields, string $propertyPath): void
    {
        self::assertSame([$propertyPath], self::paths(self::payload($fields)));
    }

    public function testTheLimitsAreAtTheEdgeOfWhatIsAccepted(): void
    {
        $email = str_repeat('a', 64).'@'.str_repeat('b', 62).'.'.str_repeat('c', 62).'.'.str_repeat('d', 58).'.test';

        self::assertSame([], self::paths(self::payload(['name' => str_repeat('a', 100), 'email' => $email, 'message' => str_repeat('a', 4000)])));
    }

    /**
     * @param array<string, string> $fields
     */
    private static function payload(array $fields): ContactPayload
    {
        return new ContactPayload(...[...['name' => 'Lou Fern', 'email' => 'lou@example.test', 'message' => 'Hello', 'website' => ''], ...$fields]);
    }

    /**
     * @return list<string>
     */
    private static function paths(ContactPayload $payload): array
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        return array_values(array_unique(array_map(static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(), iterator_to_array($validator->validate($payload)))));
    }
}
