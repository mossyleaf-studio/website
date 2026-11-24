<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Domain\Identity\UserRepository;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FakeAccounts;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Ulid;

final class AuthenticationTest extends WebTestCase
{
    use ActsAsUser;

    private const array EDITOR = ['sub' => 'account-1', 'email' => 'fern@example.com', 'name' => 'Fern', 'groups' => ['mossyleaf-studio']];

    public function testEveryAdminAndPreviewAddressNeedsASignedInEditor(): void
    {
        $client = self::createClient();
        $router = self::getContainer()->get(RouterInterface::class);
        $open = [];
        $walked = [];

        foreach ($router->getRouteCollection() as $name => $route) {
            $path = $route->getPath();
            $api = str_starts_with($path, '/beta/api/') || (str_starts_with($path, '/api/') && !str_starts_with($path, '/api/shop/'));
            if (!$api && !str_starts_with($path, '/admin') && !str_starts_with($path, '/beta')) {
                continue;
            }
            $url = $router->generate($name, self::parameters($route));

            foreach ($route->getMethods() ?: ['GET'] as $method) {
                $client->jsonRequest($method, $url);
                $response = $client->getResponse();
                $guarded = $api
                    ? 401 === $response->getStatusCode() && 'application/problem+json' === $response->headers->get('Content-Type')
                    : $response->isRedirect('/login');
                if (!$guarded) {
                    $open[] = "{$method} {$url} ({$name}) answered {$response->getStatusCode()}";
                }
            }
            $walked[] = $name;
        }

        self::assertSame([], $open);
        foreach (['app', 'api_products_describe', 'api_orders_ship', 'beta_shop_home', 'beta_note', 'beta_api_shop_orders_place'] as $name) {
            self::assertContains($name, $walked);
        }
    }

    public function testTheAdminSendsToTheMossyleafAccount(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/links');
        self::assertResponseRedirects('/login');
        $client->request('GET', '/login');

        $query = $this->authorizeQuery($client);
        self::assertSame(['code', 'mossyleaf-studio', 'http://localhost/login/check', 'openid email profile', 'S256'], [$query['response_type'], $query['client_id'], $query['redirect_uri'], $query['scope'], $query['code_challenge_method']]);
    }

    public function testAnEditorSignsInAndLandsWhereTheyWereGoing(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/links');
        $client->request('GET', '/login');

        $client->request('GET', '/login/check', ['state' => $this->authorizeQuery($client)['state'], 'code' => FakeAccounts::code(self::EDITOR)]);

        self::assertResponseRedirects('/admin/links');
        $client->jsonRequest('GET', '/api/admin/links');
        self::assertResponseIsSuccessful();
        self::assertSame('Fern', self::getContainer()->get(UserRepository::class)->findByAccountId('account-1')?->displayName());
    }

    public function testAnAccountOutsideTheGroupIsTurnedAway(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        $client->request('GET', '/login/check', ['state' => $this->authorizeQuery($client)['state'], 'code' => FakeAccounts::code([...self::EDITOR, 'groups' => ['mossydew']])]);
        self::assertResponseRedirects('/login');
        $client->followRedirect();

        self::assertSame('Your mossyleaf account does not have access to the mossyleaf.studio admin.', Json::string(self::props($client), 'error'));
        self::assertNull(self::getContainer()->get(UserRepository::class)->findByAccountId('account-1'));
    }

    public function testAnUnknownStateIsRefused(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        $client->request('GET', '/login/check', ['state' => 'forged', 'code' => FakeAccounts::code(self::EDITOR)]);
        $client->followRedirect();

        self::assertSame('This sign-in took too long or was opened in another tab, please try again.', Json::string(self::props($client), 'error'));
    }

    public function testSigningOutAlsoSignsOutOfTheMossyleafAccount(): void
    {
        $client = self::createClient();
        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        $client->request('GET', '/admin/texts');
        $token = Json::string(Json::decode((string) $client->getCrawler()->filter('#app-session')->text()), 'logoutToken');

        $client->request('POST', '/logout', ['_csrf_token' => $token]);

        self::assertResponseRedirects('https://accounts.test/end-session?client_id=mossyleaf-studio&post_logout_redirect_uri=http%3A%2F%2Flocalhost%2F');
        $client->jsonRequest('GET', '/api/admin/texts');
        self::assertResponseStatusCodeSame(401);
    }

    /** @return array<string, string> */
    private static function parameters(Route $route): array
    {
        $candidates = ['en', (string) new Ulid(), 'terms', 'france', 'heading'];
        $parameters = [];
        /** @var list<string> $variables */
        $variables = $route->compile()->getPathVariables();
        foreach ($variables as $variable) {
            $requirement = $route->getRequirement($variable) ?? '[^/]++';
            $parameters[$variable] = array_find($candidates, static fn (string $value): bool => 1 === preg_match('{^(?:'.$requirement.')$}', $value))
                ?? self::fail("No sample value for {$variable} of {$route->getPath()}");
        }

        return $parameters;
    }

    /**
     * @return array<string, string>
     */
    private function authorizeQuery(KernelBrowser $client): array
    {
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('https://accounts.test/authorize?', $location);
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);

        $strings = [];
        foreach ($query as $name => $value) {
            $strings[(string) $name] = \is_string($value) ? $value : '';
        }

        return $strings;
    }

    /**
     * @return array<mixed>
     */
    private static function props(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getCrawler()->filter('#auth')->attr('data-props'));
    }
}
