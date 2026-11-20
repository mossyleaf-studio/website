<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class ShopContactApiTest extends WebTestCase
{
    use SignsInClient;

    private const string STUDIO = 'studio@mossyleaf.test';
    private const string URL = '/beta/api/shop/fr/contact';

    protected function tearDown(): void
    {
        $_ENV['STUDIO_EMAIL'] = self::STUDIO;
        Request::setTrustedProxies([], 0);
        parent::tearDown();
    }

    public function testAMessageIsMailedToTheStudioWithTheVisitorAsReplyTo(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, self::message());

        self::assertResponseStatusCodeSame(204);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame([self::STUDIO], array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo()));
        self::assertSame([self::STUDIO], array_map(static fn (Address $address): string => $address->getAddress(), $email->getFrom()));
        self::assertSame(['"Lou Fern" <lou@example.test>'], array_map(static fn (Address $address): string => $address->toString(), $email->getReplyTo()));
        self::assertSame('Message de Lou Fern sur le site', $email->getSubject());
        self::assertNull($email->getHtmlBody());
        self::assertEmailTextBodyContains($email, "Bonjour,\nauriez-vous encore le renard en A4 ?");
        self::assertEmailTextBodyContains($email, 'Lou Fern <lou@example.test>');
        self::assertEmailTextBodyNotContains($email, '&lt;');
    }

    public function testTheMailIsWrittenInTheLanguageOfThePage(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/beta/api/shop/en/contact', self::message());

        self::assertResponseStatusCodeSame(204);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Message from Lou Fern on the website', $email->getSubject());
    }

    public function testAFilledHoneypotAnswersLikeASuccessAndSendsNothing(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, [...self::message(), 'website' => 'https://spam.test']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $client->getResponse()->getContent());
        self::assertEmailCount(0);
    }

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

    /** @param array<string, string> $fields */
    #[DataProvider('refusedFields')]
    public function testEachFieldViolationIsRefusedAndNoMailLeaves(array $fields, string $propertyPath): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, [...self::message(), ...$fields]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([$propertyPath], array_values(array_unique(array_map(static fn (mixed $violation): string => Json::string($violation, 'propertyPath'), Json::array(self::body($client), 'violations')))));
        self::assertEmailCount(0);
    }

    public function testTheLimitsAreAtTheEdgeOfWhatIsAccepted(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, ['name' => str_repeat('a', 100), 'email' => str_repeat('a', 64).'@'.str_repeat('b', 62).'.'.str_repeat('c', 62).'.'.str_repeat('d', 58).'.test', 'message' => str_repeat('a', 4000), 'website' => '']);

        self::assertResponseStatusCodeSame(204);
        self::assertEmailCount(1);
    }

    public function testEachMessageCountsAgainstTheAddressOfTheVisitor(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, self::message(), ['REMOTE_ADDR' => '203.0.113.7']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(4, self::contactLimiter()->create('203.0.113.7')->consume(0)->getRemainingTokens());
        self::assertSame(5, self::contactLimiter()->create('203.0.113.8')->consume(0)->getRemainingTokens());
    }

    public function testTheSixthMessageOfTheHourFromOneAddressIsRefused(): void
    {
        $client = self::signedInClient();
        self::contactLimiter()->create('203.0.113.7')->consume(5);

        $client->jsonRequest('POST', self::URL, self::message(), ['REMOTE_ADDR' => '203.0.113.7']);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('problem.too_many_requests', Json::string(self::body($client), 'code'));
        $retryAfter = (int) $client->getResponse()->headers->get('Retry-After');
        self::assertGreaterThan(0, $retryAfter);
        self::assertLessThanOrEqual(3601, $retryAfter);
        self::assertEmailCount(0);
    }

    public function testAnotherAddressStillPassesWhenOneIsLimited(): void
    {
        $client = self::signedInClient();
        self::contactLimiter()->create('203.0.113.7')->consume(5);

        $client->jsonRequest('POST', self::URL, self::message(), ['REMOTE_ADDR' => '203.0.113.8']);

        self::assertResponseStatusCodeSame(204);
        self::assertEmailCount(1);
    }

    public function testWithoutAStudioAddressTheRouteIsNotFound(): void
    {
        $_ENV['STUDIO_EMAIL'] = '';
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, self::message());

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('problem.not_found', Json::string(self::body($client), 'code'));
        self::assertEmailCount(0);
    }

    public function testAClosedMailboxIsNotFoundBeforeThePayloadIsChecked(): void
    {
        $_ENV['STUDIO_EMAIL'] = '';
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, []);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('problem.not_found', Json::string(self::body($client), 'code'));
    }

    public function testAnOversizedBodyIsRefusedBeforeItIsRead(): void
    {
        $client = self::signedInClient();

        foreach ([['message' => str_repeat('a', 70_000)], ['website' => str_repeat('a', 70_000)]] as $fields) {
            $client->jsonRequest('POST', self::URL, [...self::message(), ...$fields]);

            self::assertResponseStatusCodeSame(413);
            self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
            self::assertSame('problem.too_large', Json::string(self::body($client), 'code'));
            self::assertEmailCount(0);
        }
    }

    public function testTheVisitorBehindTheTrustedProxyIsCountedNotTheProxy(): void
    {
        $client = self::signedInClient();
        Request::setTrustedProxies(['10.0.0.0/8'], Request::HEADER_X_FORWARDED_FOR);

        $client->jsonRequest('POST', self::URL, self::message(), ['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(4, self::contactLimiter()->create('203.0.113.7')->consume(0)->getRemainingTokens());
        self::assertSame(5, self::contactLimiter()->create('10.0.0.2')->consume(0)->getRemainingTokens());
    }

    public function testTheShutDoorHidesTheOtherMethodsOfThePublicRoute(): void
    {
        $client = self::createClient();

        foreach (['GET', 'PUT', 'DELETE'] as $method) {
            $client->jsonRequest($method, '/api/shop/en/contact');

            self::assertResponseStatusCodeSame(404, $method);
            self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
            self::assertSame('problem.not_found', Json::string(self::body($client), 'code'));
            self::assertResponseNotHasHeader('Allow', $method);
        }
    }

    public function testTheShutDoorHidesThePublicRouteWithoutStartingASession(): void
    {
        $client = self::createClient();

        foreach (['/api/shop/fr/contact', '/api/shop/en/contact', '/api/shop/fr/contact?preview=1'] as $url) {
            $client->jsonRequest('POST', $url, self::message(), ['HTTP_PREVIEW' => '1']);

            self::assertResponseStatusCodeSame(404, $url);
            self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
            self::assertSame('problem.not_found', Json::string(self::body($client), 'code'));
            self::assertResponseNotHasHeader('Set-Cookie', $url);
            self::assertEmailCount(0);
        }
    }

    public function testThePreviewRouteAnswersSignedOutWithoutASession(): void
    {
        $client = self::createClient();

        foreach ([self::URL, '/beta/%61pi/shop/fr/contact'] as $url) {
            $client->jsonRequest('POST', $url, self::message());

            self::assertResponseStatusCodeSame(401, $url);
            self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
            self::assertEmailCount(0);
        }
    }

    public function testAMessageFromAnotherOriginIsRefused(): void
    {
        $client = self::signedInClient();

        foreach ([self::URL, '/beta/%61pi/shop/fr/contact', '/%62eta/api/shop/fr/contact'] as $url) {
            $client->jsonRequest('POST', $url, self::message(), ['HTTP_ORIGIN' => 'https://evil.test']);

            self::assertResponseStatusCodeSame(403, $url);
            self::assertEmailCount(0);
        }

        $client->jsonRequest('POST', self::URL, self::message(), ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(403);
    }

    /** @return array<string, string> */
    private static function message(): array
    {
        return ['name' => 'Lou Fern', 'email' => 'lou@example.test', 'message' => "Bonjour,\nauriez-vous encore le renard en A4 ?", 'website' => ''];
    }

    private static function contactLimiter(): RateLimiterFactoryInterface
    {
        return self::getContainer()->get('limiter.contact');
    }

    /** @return array<mixed> */
    private static function body(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getResponse()->getContent());
    }
}
