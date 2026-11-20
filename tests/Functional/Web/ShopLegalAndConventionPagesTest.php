<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Presentation\Web\Shop\SiteLabels;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;

final class ShopLegalAndConventionPagesTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use SignsInClient;

    public function testTheNewPagesStayClosedToVisitorsWithoutStartingASession(): void
    {
        $client = self::createClient();

        foreach (['/fr/conventions', '/en/conventions', '/fr/legal/terms', '/en/legal/privacy', '/fr/legal/cookies'] as $url) {
            $client->request('GET', $url);

            self::assertResponseRedirects('/', 302, $url);
            self::assertResponseNotHasHeader('Set-Cookie', $url);
        }
    }

    public function testTheirPreviewNeedsSignIn(): void
    {
        $client = self::createClient();

        foreach (['/beta/fr/conventions', '/beta/fr/legal/terms'] as $url) {
            $client->request('GET', $url);

            self::assertResponseRedirects('/login', null, $url);
        }
    }

    public function testTheConventionsPageListsTheUpcomingOnesByStartDateInTheLanguageOfThePage(): void
    {
        self::mockTime('2027-07-10 23:30 UTC');
        $client = self::signedInClient();
        self::addConvention($client, 'Later', '2027-09-01', '2027-09-02', 'Stand 4', 'Stand 4, près des fenêtres', 'https://later.example/');
        self::addConvention($client, 'Ended yesterday', '2027-07-01', '2027-07-10');
        self::addConvention($client, 'Ends today', '2027-07-09', '2027-07-11', 'Hall 5');

        $crawler = $client->request('GET', '/beta/fr/conventions');

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('Conventions · mossyleaf.studio', $crawler->filter('title')->text());
        $props = Json::decode($crawler->filter('#site-content')->text());
        $data = Json::array($props, 'data');
        self::assertSame(['page', 'title', 'conventions'], array_keys($data));
        self::assertSame(['conventions', 'Conventions'], [$data['page'], $data['title']]);
        self::assertSame(['id', 'name', 'place', 'startsOn', 'endsOn', 'url', 'note'], array_keys(Json::array($data, 'conventions', 0)));
        self::assertSame(
            [['Ends today', 'Paris', '2027-07-09', '2027-07-11', null, 'Hall 5'], ['Later', 'Paris', '2027-09-01', '2027-09-02', 'https://later.example/', 'Stand 4, près des fenêtres']],
            array_map(static fn (mixed $convention): array => array_values(\array_slice(Json::array($convention), 1)), Json::array($data, 'conventions')),
        );
        self::assertContains(['key' => 'conventions', 'url' => '/beta/fr/conventions', 'current' => true], Json::array($props, 'layout', 'navigation'));

        $shop = $crawler->filter('#shop main');
        self::assertSame('Conventions', $shop->filter('h1')->text());
        self::assertSame(['Ends today', 'Later'], $shop->filter('[data-conventions] h2')->each(static fn (Crawler $name): string => $name->text()));
        self::assertSame(['9–11 juillet 2027', '1–2 septembre 2027'], $shop->filter('[data-conventions] time')->each(static fn (Crawler $time): string => $time->text()));
        self::assertSame(['2027-07-09', '2027-09-01'], $shop->filter('[data-conventions] time')->each(static fn (Crawler $time): string => (string) $time->attr('datetime')));
        self::assertSame([[self::label('fr', 'conventions.website'), 'https://later.example/']], $shop->filter('[data-conventions] a')->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href')]));
        self::assertEveryInternalLinkStaysInThePreview($crawler);

        $english = Json::array(Json::decode($client->request('GET', '/beta/en/conventions')->filter('#site-content')->text()), 'data');
        self::assertSame('Stand 4', Json::string($english, 'conventions', 1, 'note'));
    }

    public function testThePrerenderOfTheConventionsShowsWhatThePropsCarry(): void
    {
        self::mockTime('2027-07-10 12:00');
        $client = self::signedInClient();
        self::addConvention($client, 'Salon du dessin', '2027-07-30', '2027-08-01', 'Stand 4', 'Stand 4, près des fenêtres', 'https://salon.example/');
        self::addConvention($client, 'Fête des mousses', '2027-12-30', '2028-01-02', '', 'Table ronde');

        $crawler = $client->request('GET', '/beta/fr/conventions');
        $conventions = Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data', 'conventions');
        $tickets = $crawler->filter('#shop main [data-conventions] > li');

        self::assertCount(\count($conventions), $tickets);
        self::assertSame(
            [
                ['Prochaine', 'Salon du dessin', '30 juillet – 1 août 2027', 'Salon du dessin', 'Paris', 'Stand 4, près des fenêtres', [[self::label('fr', 'conventions.website'), 'https://salon.example/', 'noopener']]],
                [null, 'Fête des mousses', '30 décembre 2027 – 2 janvier 2028', 'Fête des mousses', 'Paris', 'Table ronde', []],
            ],
            $tickets->each(static fn (Crawler $ticket, int $index): array => [
                $ticket->filter('.shop-prerender__tape')->count() > 0 ? $ticket->filter('.shop-prerender__tape')->text() : null,
                Json::string($conventions, $index, 'name'),
                $ticket->filter('time')->text(),
                $ticket->filter('h2')->text(),
                $ticket->filter('strong')->text(),
                $ticket->filter('p')->eq($ticket->filter('.shop-prerender__tape')->count() + 2)->text(),
                $ticket->filter('a')->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href'), (string) $link->attr('rel')]),
            ]),
        );
        self::assertSame('Prochaine', self::label('fr', 'conventions.soon'));
    }

    public function testThePrerenderOfALegalPageShowsWhatThePropsCarry(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/legal-pages/terms', ['text' => '', 'textFr' => "## Commandes\nPayez d’abord.\nPuis on expédie.\n\n- Écrivez via [le formulaire](https://mossyleaf.studio/fr/contact)\n- Sous 14 jours\n\n## Retours\nDans les 14 jours."]);

        $crawler = $client->request('GET', '/beta/fr/legal/terms');
        $data = Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data');
        $sheet = $crawler->filter('#shop main article');

        self::assertSame(Json::string($data, 'title'), $sheet->filter('h1')->text());
        self::assertSame(
            [['h2', 'Commandes'], ['p', "Payez d’abord.\nPuis on expédie."], ['ul', 'Écrivez via le formulaire|Sous 14 jours'], ['h2', 'Retours'], ['p', 'Dans les 14 jours.']],
            $sheet->filter('[data-blocks] > *')->each(static fn (Crawler $block): array => [
                $block->nodeName(),
                'ul' === $block->nodeName() ? implode('|', $block->filter('li')->each(static fn (Crawler $item): string => $item->text())) : $block->text(null, false),
            ]),
        );
        self::assertSame([['le formulaire', 'https://mossyleaf.studio/fr/contact']], $sheet->filter('[data-blocks] a')->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href')]));
    }

    public function testTheConventionsPageSaysWhenNoneIsPlanned(): void
    {
        self::mockTime('2027-07-10 12:00');
        $client = self::signedInClient();
        self::addConvention($client, 'Ended', '2027-07-01', '2027-07-09');

        $crawler = $client->request('GET', '/beta/en/conventions');

        self::assertSame([], Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data', 'conventions'));
        self::assertSame(self::label('en', 'conventions.empty'), $crawler->filter('#shop main p')->text());
    }

    public function testTheHomeCarriesTheNextConventionOrLeavesItOut(): void
    {
        self::mockTime('2027-07-10 12:00');
        $client = self::signedInClient();
        self::addConvention($client, 'Ended', '2027-07-01', '2027-07-09');

        $crawler = $client->request('GET', '/beta/fr/');
        self::assertArrayNotHasKey('nextConvention', Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data'));
        self::assertCount(0, $crawler->filter('#shop [data-next-convention]'));

        self::addConvention($client, 'Later', '2027-09-01', '2027-09-02');
        self::addConvention($client, 'Next', '2027-07-10', '2027-07-12', 'Hall 5', 'Hall 5, allée B');

        $crawler = $client->request('GET', '/beta/fr/');
        $data = Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data');
        $conventions = Json::array(Json::decode($client->request('GET', '/beta/fr/conventions')->filter('#site-content')->text()), 'data', 'conventions');
        self::assertSame($conventions[0], Json::array($data, 'nextConvention'));
        self::assertSame('Hall 5, allée B', Json::string($data, 'nextConvention', 'note'));

        $teaser = $crawler->filter('#shop main [data-next-convention]');
        self::assertSame(self::label('fr', 'conventions.next'), $teaser->attr('aria-label'));
        self::assertSame('Next', $teaser->filter('h2')->text());
        self::assertSame('10–12 juillet 2027', $teaser->filter('time')->text());
        self::assertSame([[self::label('fr', 'conventions.all'), '/beta/fr/conventions']], $teaser->filter('a')->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href')]));
    }

    public function testALegalPagePreviewShowsTheDraftTextInItsLanguage(): void
    {
        self::mockTime('2027-06-12 22:30:00 UTC');
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/legal-pages/terms', ['text' => "## Orders\nPay first.\n\n- [Contact](https://mossyleaf.studio/contact)\n- <b>Returns</b>", 'textFr' => "## Commandes\nPayez d’abord."]);
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);
        $client->jsonRequest('PUT', '/api/admin/legal-pages/terms', ['text' => "## Orders\nPay first.\n\n- [Contact](https://mossyleaf.studio/contact)\n- <b>Returns</b>", 'textFr' => "## Commandes\nPayez d’abord, toujours."]);

        $crawler = $client->request('GET', '/beta/fr/legal/terms');

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame(['page' => 'legal', 'kind' => 'terms', 'title' => 'Conditions générales de vente', 'text' => "## Commandes\nPayez d’abord, toujours.", 'updatedAt' => '2027-06-13'], Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data'));
        self::assertSame('Conditions générales de vente · mossyleaf.studio', $crawler->filter('title')->text());
        self::assertSame(['2027-06-13', 'Mis à jour le 13 juin 2027'], [$crawler->filter('#shop main time')->attr('datetime'), $crawler->filter('#shop main time')->text()]);
        self::assertSame(['Conditions générales de vente', 'Commandes', 'Payez d’abord, toujours.'], [$crawler->filter('#shop main h1')->text(), $crawler->filter('#shop main [data-blocks] h2')->text(), $crawler->filter('#shop main [data-blocks] p')->text()]);
        self::assertEveryInternalLinkStaysInThePreview($crawler);

        $english = $client->request('GET', '/beta/en/legal/terms')->filter('#shop main [data-blocks]');
        self::assertSame(['Contact', '<b>Returns</b>'], $english->filter('li')->each(static fn (Crawler $item): string => $item->text()));
        self::assertSame('https://mossyleaf.studio/contact', $english->filter('li a')->attr('href'));
        self::assertCount(0, $english->filter('b'));
    }

    public function testALegalPageWrittenInOneLanguageReadsInTheOther(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/legal-pages/privacy', ['text' => 'No tracker.', 'textFr' => '']);
        $client->jsonRequest('PUT', '/api/admin/legal-pages/shipping-returns', ['text' => '', 'textFr' => 'Envoi sous 3 jours.']);

        self::assertSame('No tracker.', self::pageData($client, '/beta/fr/legal/privacy')['text']);
        self::assertSame('Envoi sous 3 jours.', self::pageData($client, '/beta/en/legal/shipping-returns')['text']);
    }

    public function testAnEmptyLegalPageIsNotFoundAndLeftOutOfTheFooter(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/legal-pages/terms', ['text' => '', 'textFr' => 'Payez d’abord.']);
        $client->jsonRequest('PUT', '/api/admin/legal-pages/privacy', ['text' => ' ', 'textFr' => '']);

        foreach (['/beta/en/legal/privacy', '/beta/fr/legal/legal-notice', '/beta/en/legal/cookies'] as $url) {
            $crawler = $client->request('GET', $url);

            self::assertResponseStatusCodeSame(404, $url);
            self::assertSame('not-found', Json::string(Json::decode($crawler->filter('#site-content')->text()), 'data', 'page'), $url);
        }

        $crawler = $client->request('GET', '/beta/en/');
        self::assertSame([['kind' => 'terms', 'url' => '/beta/en/legal/terms']], Json::array(Json::decode($crawler->filter('#site-content')->text()), 'layout', 'footer', 'legal'));
        self::assertSame([[self::label('en', 'legal.title.terms'), '/beta/en/legal/terms']], $crawler->filter('#shop footer nav[data-legal] a')->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href')]));
        self::assertSame(self::label('en', 'legal.footer'), $crawler->filter('#shop footer nav[data-legal]')->attr('aria-label'));
    }

    public function testNoLegalPageMeansNoLegalFooter(): void
    {
        $crawler = self::signedInClient()->request('GET', '/beta/fr/');

        self::assertSame([], Json::array(Json::decode($crawler->filter('#site-content')->text()), 'layout', 'footer', 'legal'));
        self::assertCount(0, $crawler->filter('#shop footer nav[data-legal]'));
    }

    private static function addConvention(KernelBrowser $client, string $name, string $startsOn, string $endsOn, string $note = '', string $noteFr = '', ?string $url = null): void
    {
        $client->jsonRequest('POST', '/api/admin/conventions', ['name' => $name, 'place' => 'Paris', 'startsOn' => $startsOn, 'endsOn' => $endsOn, 'url' => $url, 'note' => $note, 'noteFr' => $noteFr]);
        self::assertResponseStatusCodeSame(201, $name);
    }

    /** @return array<mixed> */
    private static function pageData(KernelBrowser $client, string $url): array
    {
        $crawler = $client->request('GET', $url);
        self::assertResponseIsSuccessful($url);

        return Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data');
    }

    private static function assertEveryInternalLinkStaysInThePreview(Crawler $crawler): void
    {
        $internal = array_filter($crawler->filter('#shop .shop-prerender > :not([data-draft]) a')->each(static fn (Crawler $link): string => (string) $link->attr('href')), static fn (string $href): bool => str_starts_with($href, '/'));
        self::assertNotEmpty($internal);
        foreach ($internal as $href) {
            self::assertStringStartsWith('/beta/', $href);
        }
        $props = $crawler->filter('#site-content')->text();
        self::assertSame(0, preg_match('{"url":"/(?!beta/|media/)}', $props), $props);
    }

    private static function label(string $locale, string $key): string
    {
        $label = self::getContainer()->get(SiteLabels::class)->label($locale, $key);
        self::assertNotSame($key, $label, "{$locale} {$key}");

        return $label;
    }
}
