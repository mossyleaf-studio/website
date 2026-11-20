<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Domain\Content\Convention;
use App\Domain\Content\LegalKind;
use App\Domain\Content\LegalPage;
use App\Domain\Sales\ShippingZone;
use App\Domain\Sales\Zone;
use App\Domain\Shared\Translation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;

final class ShopContentFixtures extends Fixture
{
    private const array LEGAL = [
        'terms' => [
            "## Placeholder terms of sale\nReplace this text with the real terms of sale.\n\n## Orders\n- Prices include VAT\n- An order is confirmed by email once paid\n\nQuestions: [contact the studio](https://example.com/contact).",
            "## Conditions de vente provisoires\nRemplacez ce texte par les vraies conditions de vente.\n\n## Commandes\n- Les prix sont TTC\n- Une commande est confirmée par e-mail une fois payée\n\nQuestions : [contacter le studio](https://example.com/contact).",
        ],
        'legal-notice' => [
            "## Placeholder legal notice\nReplace this text with the studio's legal details.\n\n## Publisher\n- Studio name\n- Postal address\n\nSee [the studio site](https://example.com).",
            "## Mentions légales provisoires\nRemplacez ce texte par les informations légales du studio.\n\n## Éditeur\n- Nom du studio\n- Adresse postale\n\nVoir [le site du studio](https://example.com).",
        ],
        'privacy' => [
            "## Placeholder privacy policy\nReplace this text with the real privacy policy.\n\n## Data kept\n- Name and address of an order\n- Email for the confirmation\n\nMore on [the CNIL site](https://www.cnil.fr).",
            "## Politique de confidentialité provisoire\nRemplacez ce texte par la vraie politique de confidentialité.\n\n## Données conservées\n- Nom et adresse d'une commande\n- E-mail pour la confirmation\n\nPlus d'informations sur [le site de la CNIL](https://www.cnil.fr).",
        ],
        'shipping-returns' => [
            "## Placeholder shipping and returns\nReplace this text with the real delivery and returns rules.\n\n## Delivery\n- France: 3 to 5 working days\n- Abroad: 5 to 15 working days\n\nReturns within 14 days: [write to us](https://example.com/contact).",
            "## Livraison et retours provisoires\nRemplacez ce texte par les vraies règles de livraison et de retour.\n\n## Livraison\n- France : 3 à 5 jours ouvrés\n- Étranger : 5 à 15 jours ouvrés\n\nRetours sous 14 jours : [écrivez-nous](https://example.com/contact).",
        ],
    ];

    private const array ZONES = [
        'france' => [450, 4000],
        'eu' => [900, null],
        'world' => [1500, null],
    ];

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $now = $this->clock->now();
        $day = static fn (string $shift): \DateTimeImmutable => $now->setTime(0, 0)->modify($shift);

        $manager->persist(Convention::plan('Past drawing fair', 'Lyon', $day('-40 days'), $day('-38 days'), null, Translation::of('Already over.', 'Déjà terminé.')));
        $manager->persist(Convention::plan('Moss and ferns market', 'Parc de la Tête d’Or, Lyon', $day('+30 days'), $day('+32 days'), 'https://example.com/market', Translation::of('Table 12, near the pond.', 'Table 12, près de l’étang.')));
        $manager->persist(Convention::plan('Illustration festival', 'Halle Tony Garnier, Lyon', $day('+90 days'), $day('+91 days'), null, Translation::of('Prints and stickers.', 'Affiches et stickers.')));

        foreach (self::LEGAL as $kind => [$en, $fr]) {
            $page = LegalPage::blank(LegalKind::from($kind));
            $page->write(Translation::of($en, $fr), $now);
            $manager->persist($page);
        }

        foreach (self::ZONES as $code => [$price, $freeFrom]) {
            $zone = ShippingZone::off(Zone::from($code));
            $zone->set($price, $freeFrom, true);
            $manager->persist($zone);
        }

        $manager->flush();
    }
}
