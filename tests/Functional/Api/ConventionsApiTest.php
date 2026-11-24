<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Ulid;

final class ConventionsApiTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use SignsInClient;

    private const array JAPAN_EXPO = ['name' => 'Japan Expo', 'place' => 'Paris Nord Villepinte', 'startsOn' => '2027-07-08', 'endsOn' => '2027-07-11', 'url' => 'https://www.japan-expo-paris.com/', 'note' => 'Hall 5', 'noteFr' => 'Hall 5, stand B12'];

    public function testAConventionIsAddedRevisedAndRemoved(): void
    {
        self::mockTime('2027-06-01 12:00');
        $client = self::signedInClient();
        self::assertSame([], self::conventions($client));

        $client->jsonRequest('POST', '/api/admin/conventions', self::JAPAN_EXPO);
        self::assertResponseStatusCodeSame(201);
        $convention = Json::decode((string) $client->getResponse()->getContent());
        self::assertTrue(Ulid::isValid(Json::string($convention, 'id')));
        self::assertEquals(['id' => $convention['id'], ...self::JAPAN_EXPO, 'upcoming' => true], $convention);
        self::assertSame([$convention], self::conventions($client));

        $client->jsonRequest('PUT', '/api/admin/conventions/'.Json::string($convention, 'id'), [...self::JAPAN_EXPO, 'endsOn' => '2027-07-12', 'url' => '', 'noteFr' => '']);
        self::assertResponseIsSuccessful();
        self::assertSame(['2027-07-12', null, ''], [Json::string(self::conventions($client), 0, 'endsOn'), Json::at(self::conventions($client), 0, 'url'), Json::string(self::conventions($client), 0, 'noteFr')]);

        $client->jsonRequest('DELETE', '/api/admin/conventions/'.Json::string($convention, 'id'));
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], self::conventions($client));

        $client->jsonRequest('DELETE', '/api/admin/conventions/'.Json::string($convention, 'id'));
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheAdminListsTheUpcomingConventionsFirstThenThePastOnes(): void
    {
        self::mockTime('2027-07-10 23:30 UTC');
        $client = self::signedInClient();
        foreach ([['Ended yesterday', '2027-07-01', '2027-07-10'], ['Later', '2027-09-01', '2027-09-02'], ['Long ago', '2026-05-01', '2026-05-02'], ['Ends today', '2027-07-09', '2027-07-11']] as [$name, $startsOn, $endsOn]) {
            $client->jsonRequest('POST', '/api/admin/conventions', [...self::JAPAN_EXPO, 'name' => $name, 'startsOn' => $startsOn, 'endsOn' => $endsOn]);
            self::assertResponseStatusCodeSame(201, $name);
        }

        self::assertSame(
            [['Ends today', true], ['Later', true], ['Ended yesterday', false], ['Long ago', false]],
            array_map(static fn (mixed $convention): array => [Json::string($convention, 'name'), Json::at($convention, 'upcoming')], self::conventions($client)),
        );
    }

    public function testAConventionThatEndsBeforeItStartsIsRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/conventions', [...self::JAPAN_EXPO, 'endsOn' => '2027-07-07']);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('content.convention_ends_before_start', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'code'));
        self::assertSame([], self::conventions($client));
    }

    /** @param array<string, mixed> $change */
    #[DataProvider('refusedFields')]
    public function testAFieldOutsideItsRulesIsRefused(array $change, string $field): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/conventions', [...self::JAPAN_EXPO, ...$change]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([$field], array_column(Json::array(Json::decode((string) $client->getResponse()->getContent()), 'violations'), 'propertyPath'));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function refusedFields(): iterable
    {
        yield 'http address' => [['url' => 'http://www.japan-expo-paris.com/'], 'url'];
        yield 'not a day' => [['startsOn' => '2027-02-30'], 'startsOn'];
    }

    public function testAnUnknownConventionIsNotFound(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/conventions/'.new Ulid(), self::JAPAN_EXPO);

        self::assertResponseStatusCodeSame(404);
    }

    /** @return list<mixed> */
    private static function conventions(KernelBrowser $client): array
    {
        $client->jsonRequest('GET', '/api/admin/conventions');
        self::assertResponseIsSuccessful();

        return array_values(Json::decode((string) $client->getResponse()->getContent()));
    }
}
