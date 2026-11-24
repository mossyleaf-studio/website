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
    private const string SENDER = 'shop@mossyleaf.test';
    private const string URL = '/beta/api/shop/fr/contact';

    protected function tearDown(): void
    {
        $_ENV['STUDIO_EMAIL'] = self::STUDIO;
        $_ENV['MAILER_FROM'] = self::SENDER;
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
        self::assertSame([self::SENDER], array_map(static fn (Address $address): string => $address->getAddress(), $email->getFrom()));
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

    public function testAnInvalidMessageIsRefusedAndNoMailLeaves(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, [...self::message(), 'email' => 'lou@']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['email'], array_column(Json::array(self::body($client), 'violations'), 'propertyPath'));
        self::assertEmailCount(0);
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function closingVariables(): iterable
    {
        yield 'no studio address' => ['STUDIO_EMAIL'];
        yield 'no sender address' => ['MAILER_FROM'];
    }

    #[DataProvider('closingVariables')]
    public function testAClosedMailboxIsNotFoundBeforeThePayloadIsChecked(string $variable): void
    {
        $_ENV[$variable] = '';
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, []);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('problem.not_found', Json::string(self::body($client), 'code'));
        self::assertEmailCount(0);
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
