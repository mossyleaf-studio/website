<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration;

use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Application\Catalogue\ReplaceCatalogue\PushedVariant;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogue;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogueHandler;
use App\Domain\Catalogue\Collection;
use App\Domain\Catalogue\Exception\ProductRefused;
use App\Domain\Catalogue\PhotoFile;
use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductPhoto;
use App\Domain\Catalogue\ProductVariant;
use App\Domain\Shared\Locale;
use App\Domain\Shared\Translation;
use App\Tests\Support\Json;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class CatalogueIntegrationTest extends WebTestCase
{
    private const string TOKEN = 'test-mossytrunk-token-0123456789abcdef';
    private const string FOX = '01JD3K7Q0000000000000000F0';
    private const string OWL = '01JD3K7Q0000000000000000F1';
    private const string FERN = '01JD3K7Q0000000000000000F2';
    private const string FOLK = '01JD3K7Q0000000000000000C0';
    private const string NIGHT = '01JD3K7Q0000000000000000C1';
    private const string MOSS = '01JD3K7Q0000000000000000C2';
    private const int MAX_BODY = 8 * 1024 * 1024;

    protected function tearDown(): void
    {
        $_ENV['MOSSYTRUNK_TOKEN'] = self::TOKEN;

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function tokensThatTurnTheIntegrationOff(): iterable
    {
        yield 'no token' => [''];
        yield 'a token shorter than 32 characters' => [str_repeat('a', 31)];
    }

    #[DataProvider('tokensThatTurnTheIntegrationOff')]
    public function testTheIntegrationIsNotFoundUntilATokenIsConfigured(string $configured): void
    {
        $_ENV['MOSSYTRUNK_TOKEN'] = $configured;
        $client = self::createClient();

        self::push($client, [self::fox()], $configured);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, self::rows('product'));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function refusedCredentials(): iterable
    {
        yield 'no header' => [[]];
        yield 'another token' => [['HTTP_AUTHORIZATION' => 'Bearer '.str_repeat('a', 38)]];
        yield 'the start of the token' => [['HTTP_AUTHORIZATION' => 'Bearer '.substr(self::TOKEN, 0, 32)]];
        yield 'an empty token' => [['HTTP_AUTHORIZATION' => 'Bearer ']];
        yield 'another scheme' => [['HTTP_AUTHORIZATION' => 'Basic '.self::TOKEN]];
        yield 'the token alone' => [['HTTP_AUTHORIZATION' => self::TOKEN]];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('refusedCredentials')]
    public function testAMissingOrWrongTokenIsRefused(array $server): void
    {
        $client = self::createClient();

        $client->jsonRequest('PUT', '/integrations/catalogue', ['products' => [self::fox()]], $server);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
        self::assertSame(0, self::rows('product'));
    }

    public function testEveryAddressUnderIntegrationsAsksForTheToken(): void
    {
        $client = self::createClient();

        foreach (['/integrations/catalogue', '/%69ntegrations/catalogue', '/integrations/nothing-here'] as $url) {
            $client->jsonRequest('PUT', $url, ['products' => [self::fox()]]);
            self::assertResponseStatusCodeSame(401, $url);
        }
        $client->request('GET', '/integrations/catalogue');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAFirstPushCreatesHiddenProducts(): void
    {
        $client = self::createClient();

        self::push($client, [self::fox(), self::owl()]);

        self::assertResponseIsSuccessful();
        self::assertSame(['created' => 2, 'updated' => 0, 'retired' => 0], Json::decode((string) $client->getResponse()->getContent()));
        $fox = self::product(self::FOX);
        self::assertSame(['PRT-FOX', 'Fox', 'Prints Fox', 1500, 'prints-fox', 0], [$fox->reference(), $fox->name(), $fox->displayName(), $fox->price(), $fox->slug(), $fox->position()]);
        self::assertFalse($fox->isVisible());
        self::assertFalse($fox->isRetired());
        self::assertSame([['PRT-FOX-A4', 'A4', 12], ['PRT-FOX-A5', 'A5', 0]], self::variants($fox));
        self::assertSame(['Prints', 'Prints', ''], [$fox->category()->name(), $fox->category()->labelIn(Locale::Fr), $fox->category()->label()->in(Locale::En)]);
        $owl = self::product(self::OWL);
        self::assertSame(['stickers-owl', 1, 1], [$owl->slug(), $owl->position(), $owl->category()->position()]);
    }

    public function testTheEndpointStartsNoSession(): void
    {
        $client = self::createClient();

        self::push($client, [self::fox()]);

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Set-Cookie');
        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testAPushCannotReadTheAdminApiThroughTheRefreshHeader(): void
    {
        $client = self::createClient();

        $client->jsonRequest('PUT', '/integrations/catalogue', ['products' => [self::fox()]], ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN, 'HTTP_X_REFRESH' => '["/api/admin/texts","/api/admin/links"]']);

        self::assertResponseIsSuccessful();
        self::assertSame(['created' => 1, 'updated' => 0, 'retired' => 0], Json::decode((string) $client->getResponse()->getContent()));
        self::assertResponseNotHasHeader('X-Refreshed');
    }

    public function testAPushLocksTheCatalogueSoThatTwoPushesNeverInterleave(): void
    {
        $client = self::createClient();
        self::assertSame(0, self::catalogueLocks());

        self::push($client, [self::fox()]);

        self::assertResponseIsSuccessful();
        self::assertSame(1, self::catalogueLocks());
    }

    public function testASecondPushUpdatesMossytrunkFieldsAndKeepsTheWebsiteOnes(): void
    {
        $client = self::createClient();
        self::push($client, [self::fox(), self::owl()]);
        $fox = self::product(self::FOX);
        $fox->describe(Translation::of('Fox print', 'Affiche renard'), Translation::of('A fox among ferns.', 'Un renard dans les fougères.'));
        $fox->show();
        $fox->pick();
        $fox->moveTo(5);
        $fox->useSlug('fox-print');
        $photo = $fox->addPhoto(new Ulid(), new PhotoFile('01JD3K7Q0000000000000000P0.webp', 1600, 1200), new PhotoFile('01JD3K7Q0000000000000000P1.webp', 600, 450));
        self::entityManager()->flush();

        self::push($client, [
            self::fox(['reference' => 'STK-FOX', 'name' => 'Sleeping fox', 'category' => 'Stickers', 'displayName' => 'Stickers Sleeping fox', 'price' => 400, 'variants' => [
                ['name' => 'A5', 'sku' => 'PRT-FOX-A5', 'stock' => 7],
                ['name' => 'A3', 'sku' => 'STK-FOX-A3', 'stock' => 2],
            ]]),
            self::owl(),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(['created' => 0, 'updated' => 2, 'retired' => 0], Json::decode((string) $client->getResponse()->getContent()));
        self::assertSame(2, self::rows('product'));
        $fox = self::product(self::FOX);
        self::assertSame(['STK-FOX', 'Sleeping fox', 'Stickers Sleeping fox', 400, 'Stickers'], [$fox->reference(), $fox->name(), $fox->displayName(), $fox->price(), $fox->category()->name()]);
        self::assertSame([['PRT-FOX-A5', 'A5', 7], ['STK-FOX-A3', 'A3', 2]], self::variants($fox));
        self::assertSame(['fox-print', 'Affiche renard', 'A fox among ferns.', 5], [$fox->slug(), $fox->title()->in(Locale::Fr), $fox->description()->in(Locale::En), $fox->position()]);
        self::assertTrue($fox->isVisible());
        self::assertTrue($fox->isPicked());
        self::assertFalse(self::product(self::OWL)->isPicked());
        self::assertSame([[(string) $photo->id(), '01JD3K7Q0000000000000000P0.webp', '01JD3K7Q0000000000000000P1.webp']], array_map(static fn (ProductPhoto $kept): array => [(string) $kept->id(), $kept->file(), $kept->thumbFile()], $fox->photos()));
        self::assertSame(2, self::rows('category'));
    }

    public function testAProductLeftOutIsRetiredAndComesBackWhenPushedAgain(): void
    {
        $client = self::createClient();
        self::push($client, [self::fox(), self::owl()]);
        self::product(self::FOX)->pick();
        self::entityManager()->flush();

        self::push($client, [self::owl()]);

        self::assertSame(['created' => 0, 'updated' => 1, 'retired' => 1], Json::decode((string) $client->getResponse()->getContent()));
        self::assertTrue(self::product(self::FOX)->isRetired());
        self::assertTrue(self::product(self::FOX)->isPicked());
        self::assertSame([], self::variants(self::product(self::FOX)));
        self::assertFalse(self::product(self::OWL)->isRetired());

        self::push($client, [self::owl()]);

        self::assertSame(['created' => 0, 'updated' => 1, 'retired' => 0], Json::decode((string) $client->getResponse()->getContent()));

        self::push($client, [self::fox(), self::owl()]);

        self::assertSame(['created' => 0, 'updated' => 2, 'retired' => 0], Json::decode((string) $client->getResponse()->getContent()));
        $fox = self::product(self::FOX);
        self::assertFalse($fox->isRetired());
        self::assertTrue($fox->isPicked());
        self::assertSame('prints-fox', $fox->slug());
        self::assertSame([['PRT-FOX-A4', 'A4', 12], ['PRT-FOX-A5', 'A5', 0]], self::variants($fox));
        self::assertSame(2, self::rows('product'));
    }

    public function testAChangedReferenceWithTheSameIdStaysTheSameProduct(): void
    {
        $client = self::createClient();
        self::push($client, [self::fern()]);
        $id = (string) self::product(self::FERN)->id();

        self::push($client, [self::fern(['reference' => 'PIN-FERN-2', 'variants' => [['name' => null, 'sku' => 'PIN-FERN-2', 'stock' => 29]]])]);

        self::assertSame(['created' => 0, 'updated' => 1, 'retired' => 0], Json::decode((string) $client->getResponse()->getContent()));
        $fern = self::product(self::FERN);
        self::assertSame([$id, 'PIN-FERN-2', 'pins-fern'], [(string) $fern->id(), $fern->reference(), $fern->slug()]);
        self::assertSame([['PIN-FERN-2', null, 29]], self::variants($fern));
        self::assertSame(1, self::rows('product'));
    }

    public function testReferencesAndSkusMoveBetweenProductsInOnePush(): void
    {
        $client = self::createClient();
        self::push($client, [self::fox(), self::fern()]);

        self::push($client, [
            self::fox(['reference' => 'PIN-FERN', 'variants' => [['name' => null, 'sku' => 'PIN-FERN', 'stock' => 1]]]),
            self::fern(['reference' => 'PRT-FOX', 'variants' => [['name' => 'A4', 'sku' => 'PRT-FOX-A4', 'stock' => 2]]]),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([['PIN-FERN', null, 1]], self::variants(self::product(self::FOX)));
        self::assertSame([['PRT-FOX-A4', 'A4', 2]], self::variants(self::product(self::FERN)));
    }

    public function testAProductRecreatedInMossytrunkTakesOverTheReferenceOfTheRetiredOne(): void
    {
        $client = self::createClient();
        self::push($client, [self::fox(), self::owl()]);

        self::push($client, [self::fox(['id' => self::FERN]), self::owl()]);

        self::assertSame(['created' => 1, 'updated' => 1, 'retired' => 1], Json::decode((string) $client->getResponse()->getContent()));
        self::assertTrue(self::product(self::FOX)->isRetired());
        $again = self::product(self::FERN);
        self::assertSame(['PRT-FOX', 'prints-fox-2'], [$again->reference(), $again->slug()]);
        self::assertSame([['PRT-FOX-A4', 'A4', 12], ['PRT-FOX-A5', 'A5', 0]], self::variants($again));
    }

    public function testSlugsAreBuiltOnceFromTheDisplayNameAndNumberedOnCollision(): void
    {
        $client = self::createClient();

        self::push($client, [
            self::fox(['displayName' => 'Carte « Renard & Fougère »']),
            self::owl(['displayName' => 'Carte Renard Fougère']),
            self::fern(['displayName' => '★']),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(['carte-renard-fougere', 'carte-renard-fougere-2', 'product'], [self::product(self::FOX)->slug(), self::product(self::OWL)->slug(), self::product(self::FERN)->slug()]);
    }

    public function testTheLongestValuesMossytrunkCanHoldAreAccepted(): void
    {
        $client = self::createClient();
        $reference = str_repeat('R', 64);
        $variant = str_repeat('é', 255);

        self::push($client, [self::fox([
            'reference' => $reference,
            'name' => str_repeat('n', 255),
            'category' => str_repeat('c', 100),
            'displayName' => str_repeat('c', 100).' '.str_repeat('n', 255),
            'price' => 2147483647,
            'variants' => [['name' => $variant, 'sku' => $reference.'-'.$variant, 'stock' => 2147483647]],
        ])]);

        self::assertResponseIsSuccessful();
        self::assertLessThanOrEqual(Product::MAX_SLUG, mb_strlen(self::product(self::FOX)->slug()));
    }

    public function testANewProductArrivesHiddenWithThePushedDescriptions(): void
    {
        $client = self::createClient();

        self::push($client, [self::fox(['descriptions' => ['fr' => "  Un renard.\nDans les fougères.\n\nTirage A4.  ", 'en' => 'A fox among ferns.']])]);

        self::assertResponseIsSuccessful();
        self::assertSame(['created' => 1, 'updated' => 0, 'retired' => 0], Json::decode((string) $client->getResponse()->getContent()));
        $fox = self::product(self::FOX);
        self::assertSame(["Un renard.\nDans les fougères.\n\nTirage A4.", 'A fox among ferns.'], [$fox->description()->fr, $fox->description()->en]);
        self::assertSame(['', '', 'prints-fox', 0], [$fox->title()->fr, $fox->title()->en, $fox->slug(), $fox->position()]);
        self::assertFalse($fox->isVisible());
    }

    public function testALaterPushFillsAnEmptyDescription(): void
    {
        $client = self::createClient();
        self::push($client, [self::fox()]);

        self::push($client, [self::fox(['descriptions' => ['fr' => 'Un renard.', 'en' => 'A fox.']])]);

        self::assertSame(['created' => 0, 'updated' => 1, 'retired' => 0], Json::decode((string) $client->getResponse()->getContent()));
        self::assertSame(['Un renard.', 'A fox.'], [self::product(self::FOX)->description()->fr, self::product(self::FOX)->description()->en]);
    }

    public function testADescriptionTheWebsiteHoldsIsKeptInEachLanguage(): void
    {
        $client = self::createClient();
        self::push($client, [self::fox()]);
        self::product(self::FOX)->describe(Translation::of('', 'Affiche renard'), Translation::of('', 'Écrit par le studio.'));
        self::entityManager()->flush();

        self::push($client, [self::fox(['descriptions' => ['fr' => 'Texte Etsy.', 'en' => 'Etsy text.']])]);

        self::assertResponseIsSuccessful();
        $fox = self::product(self::FOX);
        self::assertSame(['Écrit par le studio.', 'Etsy text.', 'Affiche renard'], [$fox->description()->fr, $fox->description()->en, $fox->title()->fr]);

        self::push($client, [self::fox(['descriptions' => ['fr' => 'Autre texte.', 'en' => 'Another text.']])]);

        self::assertSame(['Écrit par le studio.', 'Etsy text.'], [self::product(self::FOX)->description()->fr, self::product(self::FOX)->description()->en]);
    }

    public function testABlankTextOrAnUnknownLanguageChangesNothing(): void
    {
        $client = self::createClient();

        self::push($client, [self::fox(['descriptions' => ['fr' => " \n\t ", 'de' => 'Ein Fuchs.', 'EN' => 'A fox.']]), self::owl(['descriptions' => []])]);

        self::assertResponseIsSuccessful();
        self::assertSame(['', ''], [self::product(self::FOX)->description()->fr, self::product(self::FOX)->description()->en]);
        self::assertSame(['', ''], [self::product(self::OWL)->description()->fr, self::product(self::OWL)->description()->en]);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function malformedDescriptions(): iterable
    {
        $notTexts = 'products[1].descriptions: Descriptions are texts keyed by language, such as {"fr": "…", "en": "…"}.';
        yield 'a list' => [['Un renard.'], $notTexts];
        yield 'a text' => ['Un renard.', 'products[1].descriptions: This value should be of type array.'];
    }

    #[DataProvider('malformedDescriptions')]
    public function testMalformedDescriptionsRefuseTheCatalogue(mixed $descriptions, string $detail): void
    {
        $client = self::createClient();
        self::push($client, [self::fox(), self::owl()]);

        self::push($client, [self::owl(['descriptions' => ['fr' => 'Une chouette.']]), self::fox(['descriptions' => $descriptions])]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($detail, Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
        self::assertNothingChanged();
        self::assertSame(['', ''], [self::product(self::OWL)->description()->fr, self::product(self::FOX)->description()->fr]);
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>}>
     */
    public static function refusedCatalogues(): iterable
    {
        yield 'an empty list' => [[]];
        yield 'the same sku in two products' => [[self::owl(), self::fern(['variants' => [['name' => null, 'sku' => 'STK-OWL', 'stock' => 1]]])]];
        yield 'the same sku twice in a product' => [[self::owl(), self::fern(['variants' => [['name' => 'S', 'sku' => 'PIN-FERN-S', 'stock' => 1], ['name' => 'M', 'sku' => ' PIN-FERN-S ', 'stock' => 1]]])]];
        yield 'the same id twice' => [[self::owl(), self::fern(['id' => strtolower(self::OWL)])]];
        yield 'the same reference twice' => [[self::owl(), self::fern(['reference' => 'STK-OWL '])]];
        yield 'more than 50 variants' => [[self::owl(), self::fern(['variants' => array_map(static fn (int $n): array => ['name' => 'V'.$n, 'sku' => 'PIN-FERN-V'.$n, 'stock' => 1], range(1, 51))])]];
        yield 'more than 2000 products' => [array_map(static fn (int $n): array => self::fern(['id' => (string) new Ulid(), 'reference' => 'PIN-'.$n, 'variants' => [['name' => null, 'sku' => 'PIN-'.$n, 'stock' => 1]]]), range(1, 2001))];
        yield 'a reference longer than 64 characters' => [[self::owl(), self::fern(['reference' => str_repeat('R', 65)])]];
        yield 'a name longer than 255 characters' => [[self::owl(), self::fern(['name' => str_repeat('n', 256)])]];
        yield 'a display name longer than 356 characters' => [[self::owl(), self::fern(['displayName' => str_repeat('d', 357)])]];
        yield 'a blank sku' => [[self::owl(), self::fern(['variants' => [['name' => null, 'sku' => ' ', 'stock' => 1]]])]];
    }

    /**
     * @param list<array<string, mixed>> $products
     */
    #[DataProvider('refusedCatalogues')]
    public function testARefusedCatalogueChangesNothing(array $products): void
    {
        $client = self::createClient();
        self::push($client, [self::fox(), self::owl()]);

        self::push($client, $products);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame(422, Json::int(Json::decode((string) $client->getResponse()->getContent()), 'status'));
        self::assertNothingChanged();
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusalsThatNameTheProduct(): iterable
    {
        yield 'a variant name longer than 255 characters' => [self::fern(['variants' => [['name' => str_repeat('v', 256), 'sku' => 'PIN-FERN-V', 'stock' => 1]]]), 'The product “PIN-FERN” is refused: a variant name is longer than 255 characters.'];
        yield 'a sku longer than 320 characters' => [self::fern(['variants' => [['name' => null, 'sku' => str_repeat('s', 321), 'stock' => 1]]]), 'The product “PIN-FERN” is refused: a SKU is longer than 320 characters.'];
        yield 'a category longer than 100 characters' => [self::fern(['category' => str_repeat('c', 101)]), 'The product “PIN-FERN” is refused: its category is longer than 100 characters.'];
        yield 'a blank name' => [self::fern(['name' => ' ']), 'The product “PIN-FERN” is refused: its name is empty.'];
        yield 'a negative price' => [self::fern(['price' => -1]), 'The product “PIN-FERN” is refused: its price is negative.'];
        yield 'a negative stock' => [self::fern(['variants' => [['name' => null, 'sku' => 'PIN-FERN', 'stock' => -1]]]), 'The product “PIN-FERN” is refused: a stock is negative.'];
    }

    /**
     * @param array<string, mixed> $product
     */
    #[DataProvider('refusalsThatNameTheProduct')]
    public function testARefusalNamesTheProductAndTheField(array $product, string $detail): void
    {
        $client = self::createClient();

        self::push($client, [self::owl(), $product]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($detail, Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, string>}>
     */
    public static function oversizeBodies(): iterable
    {
        yield 'a body heavier than 8 MB' => [['products' => [self::fern()], 'padding' => str_repeat('x', self::MAX_BODY)], []];
        yield 'a declared length above 8 MB' => [['products' => [self::fern()]], ['CONTENT_LENGTH' => (string) (self::MAX_BODY + 1)]];
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $server
     */
    #[DataProvider('oversizeBodies')]
    public function testAnOversizeBodyIsRefusedBeforeItIsRead(array $body, array $server): void
    {
        $client = self::createClient();
        self::push($client, [self::fox(), self::owl()]);

        $client->jsonRequest('PUT', '/integrations/catalogue', $body, $server + ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('A catalogue weighs 8 MB at most.', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
        self::assertNothingChanged();

        $client->jsonRequest('PUT', '/integrations/catalogue', $body, $server);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>}>
     */
    public static function malformedCatalogues(): iterable
    {
        yield 'a price that is not a number' => [[self::owl(), self::fern(['price' => 'free'])]];
        yield 'a price beyond what the database holds' => [[self::owl(), self::fern(['price' => 2147483648])]];
        yield 'no variant at all' => [[self::owl(), self::fern(['variants' => []])]];
        yield 'an id that is not a ULID' => [[self::owl(), self::fern(['id' => 'fern'])]];
    }

    /**
     * @param list<array<string, mixed>> $products
     */
    #[DataProvider('malformedCatalogues')]
    public function testAMalformedCatalogueChangesNothing(array $products): void
    {
        $client = self::createClient();
        self::push($client, [self::fox(), self::owl()]);

        self::push($client, $products);

        self::assertResponseStatusCodeSame(422);
        self::assertNothingChanged();
    }

    public function testABodyWithoutProductsIsRefused(): void
    {
        $client = self::createClient();
        self::push($client, [self::fox(), self::owl()]);

        $client->jsonRequest('PUT', '/integrations/catalogue', ['items' => []], ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);

        self::assertResponseStatusCodeSame(422);
        self::assertNothingChanged();
    }

    public function testAPushThatFailsHalfwayLeavesTheCatalogueAsItWas(): void
    {
        $client = self::createClient();
        self::push($client, [self::fox(), self::owl()]);
        $handler = self::getContainer()->get(ReplaceCatalogueHandler::class);

        try {
            $handler(new ReplaceCatalogue([
                new PushedProduct(new Ulid(self::OWL), 'STK-OWL', 'Owl', 'Stickers', 'Stickers Owl', 999, [new PushedVariant(null, 'STK-OWL', 1)]),
                new PushedProduct(new Ulid(self::FERN), 'PIN-FERN', str_repeat('n', 256), 'Pins', 'Pins Fern', 600, [new PushedVariant(null, 'PIN-FERN', 30)]),
            ]));
            self::fail('The push went through.');
        } catch (ProductRefused) {
        }

        self::ensureKernelShutdown();
        self::bootKernel();
        self::assertNothingChanged();
    }

    public function testAFirstPushCreatesHiddenCollectionsInTheOrderMet(): void
    {
        $client = self::createClient();

        self::push($client, [
            self::fox(['collection' => ['id' => self::FOLK, 'name' => ' Forest folk ']]),
            self::owl(['collection' => ['id' => strtolower(self::NIGHT), 'name' => 'Night owls']]),
            self::fern(['collection' => ['id' => self::FOLK, 'name' => 'Forest folk']]),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(['created' => 3, 'updated' => 0, 'retired' => 0], Json::decode((string) $client->getResponse()->getContent()));
        self::assertSame([['Forest folk', 0, false, '', ''], ['Night owls', 1, false, '', '']], self::collections());
        self::assertSame(['Forest folk', 'Night owls', 'Forest folk'], [self::product(self::FOX)->collection()?->name(), self::product(self::OWL)->collection()?->name(), self::product(self::FERN)->collection()?->name()]);
        self::assertTrue(self::product(self::OWL)->collection()?->sourceId()->equals(new Ulid(self::NIGHT)));
    }

    public function testALaterPushRenamesMovesClearsAndLeavesTheCollectionOfEachProduct(): void
    {
        $client = self::createClient();
        self::push($client, [
            self::fox(['collection' => ['id' => self::FOLK, 'name' => 'Forest folk']]),
            self::owl(['collection' => ['id' => self::NIGHT, 'name' => 'Night owls']]),
            self::fern(['collection' => ['id' => self::NIGHT, 'name' => 'Night owls']]),
        ]);

        self::push($client, [
            self::fox(['collection' => ['id' => self::FOLK, 'name' => 'Forest people']]),
            self::owl(['collection' => ['id' => self::FOLK, 'name' => 'Forest people']]),
            self::fern(),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(['created' => 0, 'updated' => 3, 'retired' => 0], Json::decode((string) $client->getResponse()->getContent()));
        self::assertSame([['Forest people', 0, false, '', ''], ['Night owls', 1, false, '', '']], self::collections());
        self::assertSame(['Forest people', 'Forest people', 'Night owls'], [self::product(self::FOX)->collection()?->name(), self::product(self::OWL)->collection()?->name(), self::product(self::FERN)->collection()?->name()]);

        self::push($client, [self::fox(), self::owl(['collection' => null]), self::fern(['collection' => null])]);

        self::assertResponseIsSuccessful();
        self::assertSame([['Forest people', 0, false, '', '']], self::collections());
        self::assertSame(['Forest people', null, null], [self::product(self::FOX)->collection()?->name(), self::product(self::OWL)->collection(), self::product(self::FERN)->collection()]);
    }

    public function testTheWebsiteSideOfACollectionSurvivesPushesAndANewOneComesLast(): void
    {
        $client = self::createClient();
        self::push($client, [
            self::fox(['collection' => ['id' => self::FOLK, 'name' => 'Forest folk']]),
            self::owl(['collection' => ['id' => self::NIGHT, 'name' => 'Night owls']]),
        ]);
        $folk = self::product(self::FOX)->collection();
        self::assertNotNull($folk);
        $folk->relabel(Translation::of('Woodland folk', 'Peuple des bois'));
        $folk->show();
        $folk->moveTo(4);
        self::entityManager()->flush();

        self::push($client, [
            self::fox(['collection' => ['id' => self::FOLK, 'name' => 'Forest people']]),
            self::owl(['collection' => ['id' => self::NIGHT, 'name' => 'Night owls']]),
            self::fern(['collection' => ['id' => self::MOSS, 'name' => 'Moss']]),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([['Night owls', 1, false, '', ''], ['Forest people', 4, true, 'Woodland folk', 'Peuple des bois'], ['Moss', 5, false, '', '']], self::collections());
    }

    public function testACollectionNoProductPointsAtIsDeletedButOneARetiredProductKeepsIsNot(): void
    {
        $client = self::createClient();
        self::push($client, [
            self::fox(['collection' => ['id' => self::FOLK, 'name' => 'Forest folk']]),
            self::owl(['collection' => ['id' => self::NIGHT, 'name' => 'Night owls']]),
            self::fern(['collection' => ['id' => self::MOSS, 'name' => 'Moss']]),
        ]);

        self::push($client, [self::owl(), self::fern(['collection' => null])]);

        self::assertResponseIsSuccessful();
        self::assertSame(['created' => 0, 'updated' => 2, 'retired' => 1], Json::decode((string) $client->getResponse()->getContent()));
        self::assertSame([['Forest folk', 0, false, '', ''], ['Night owls', 1, false, '', '']], self::collections());
        self::assertTrue(self::product(self::FOX)->isRetired());
        self::assertSame('Forest folk', self::product(self::FOX)->collection()?->name());
    }

    public function testAPushWithoutAnyCollectionKeyCreatesNone(): void
    {
        $client = self::createClient();

        self::push($client, [self::fox(), self::owl()]);

        self::assertResponseIsSuccessful();
        self::assertSame(0, self::rows('collection'));
        self::assertNull(self::product(self::FOX)->collection());
    }

    public function testTheLongestCollectionNameIsAccepted(): void
    {
        $client = self::createClient();

        self::push($client, [self::fox(['collection' => ['id' => self::FOLK, 'name' => str_repeat('é', 255)]])]);

        self::assertResponseIsSuccessful();
        self::assertSame(255, mb_strlen((string) self::product(self::FOX)->collection()?->name()));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function malformedCollections(): iterable
    {
        yield 'a text' => ['Forest folk'];
        yield 'an id that is not a ULID' => [['id' => 'forest', 'name' => 'Forest folk']];
        yield 'a blank name' => [['id' => self::FOLK, 'name' => ' ']];
        yield 'a name longer than 255 characters' => [['id' => self::FOLK, 'name' => str_repeat('é', 256)]];
    }

    #[DataProvider('malformedCollections')]
    public function testAMalformedCollectionRefusesTheCatalogue(mixed $collection): void
    {
        $client = self::createClient();
        self::push($client, [self::fox(), self::owl()]);

        self::push($client, [self::owl(['collection' => ['id' => self::NIGHT, 'name' => 'Night owls']]), self::fox(['collection' => $collection])]);

        self::assertResponseStatusCodeSame(422);
        $problem = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(422, Json::int($problem, 'status'));
        self::assertSame('products[1].collection: A collection is sent as {"id": "<ULID>", "name": "…"} or null.', Json::string($problem, 'detail'));
        self::assertNothingChanged();
        self::assertSame(0, self::rows('collection'));
    }

    private static function assertNothingChanged(): void
    {
        self::assertSame(2, self::rows('product'));
        self::assertSame(2, self::rows('category'));
        self::assertSame(3, self::rows('product_variant'));
        self::assertFalse(self::product(self::FOX)->isRetired());
        self::assertSame([['PRT-FOX-A4', 'A4', 12], ['PRT-FOX-A5', 'A5', 0]], self::variants(self::product(self::FOX)));
        self::assertSame([300, [['STK-OWL', null, 4]]], [self::product(self::OWL)->price(), self::variants(self::product(self::OWL))]);
    }

    /**
     * @param list<array<string, mixed>> $products
     */
    private static function push(KernelBrowser $client, array $products, string $token = self::TOKEN): void
    {
        $client->jsonRequest('PUT', '/integrations/catalogue', ['products' => $products], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private static function fox(array $changes = []): array
    {
        return $changes + ['id' => self::FOX, 'reference' => 'PRT-FOX', 'name' => 'Fox', 'category' => 'Prints', 'displayName' => 'Prints Fox', 'price' => 1500, 'variants' => [
            ['name' => 'A4', 'sku' => 'PRT-FOX-A4', 'stock' => 12],
            ['name' => 'A5', 'sku' => 'PRT-FOX-A5', 'stock' => 0],
        ]];
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private static function owl(array $changes = []): array
    {
        return $changes + ['id' => self::OWL, 'reference' => 'STK-OWL', 'name' => 'Owl', 'category' => 'Stickers', 'displayName' => 'Stickers Owl', 'price' => 300, 'variants' => [
            ['name' => null, 'sku' => 'STK-OWL', 'stock' => 4],
        ]];
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private static function fern(array $changes = []): array
    {
        return $changes + ['id' => self::FERN, 'reference' => 'PIN-FERN', 'name' => 'Fern', 'category' => 'Pins', 'displayName' => 'Pins Fern', 'price' => 600, 'variants' => [
            ['name' => null, 'sku' => 'PIN-FERN', 'stock' => 30],
        ]];
    }

    private static function product(string $sourceId): Product
    {
        $product = self::entityManager()->getRepository(Product::class)->findOneBy(['sourceId' => new Ulid($sourceId)]);
        self::assertNotNull($product);

        return $product;
    }

    /**
     * @return list<array{string, ?string, int}>
     */
    private static function variants(Product $product): array
    {
        return array_map(static fn (ProductVariant $variant): array => [$variant->sku(), $variant->name(), $variant->stock()], $product->variants());
    }

    /**
     * @return list<array{string, int, bool, string, string}>
     */
    private static function collections(): array
    {
        $collections = self::entityManager()->getRepository(Collection::class)->findBy([], ['position' => 'ASC']);

        return array_map(static fn (Collection $collection): array => [$collection->name(), $collection->position(), $collection->isShown(), $collection->label()->en, $collection->label()->fr], $collections);
    }

    private static function rows(string $table): int
    {
        $count = self::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private static function catalogueLocks(): int
    {
        $count = self::getContainer()->get(Connection::class)->fetchOne("SELECT COUNT(*) FROM pg_locks WHERE pid = pg_backend_pid() AND relation = 'product'::regclass AND mode = 'ShareRowExclusiveLock'");
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private static function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
