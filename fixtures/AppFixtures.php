<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Domain\Content\Link;
use App\Domain\Content\SiteText;
use App\Domain\Content\TapeTone;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $manager->persist(SiteText::initial());
        $manager->persist(Link::create('Shop on Etsy', 'Prints, stickers and illustrations', 'https://www.etsy.com/shop/mossyleafstudio', TapeTone::Leaf, 0));
        $manager->persist(Link::create('Follow on Instagram', 'New drawings and market dates', 'https://www.instagram.com/mossyleaf.studio/', TapeTone::Blossom, 1));
        $manager->flush();
    }
}
