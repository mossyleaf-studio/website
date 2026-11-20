<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120113751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'French twins of the site texts, links and image descriptions, shop notes and the frozen shop content';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artwork ADD alt_fr VARCHAR(200) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE link ADD title_fr VARCHAR(60) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE link ADD description_fr VARCHAR(120) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE published_site ADD shop_content TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE site_text ADD intro_fr VARCHAR(200) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE site_text ADD meta_description_fr VARCHAR(300) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE site_text ADD search_title_fr VARCHAR(70) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE site_text ADD about_title_fr VARCHAR(80) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE site_text ADD about_text_fr VARCHAR(2000) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE site_text ADD gallery_title_fr VARCHAR(80) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE site_text ADD announcement VARCHAR(160) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE site_text ADD announcement_fr VARCHAR(160) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE site_text ADD price_note VARCHAR(160) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE site_text ADD price_note_fr VARCHAR(160) DEFAULT \'\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artwork DROP alt_fr');
        $this->addSql('ALTER TABLE link DROP title_fr');
        $this->addSql('ALTER TABLE link DROP description_fr');
        $this->addSql('ALTER TABLE published_site DROP shop_content');
        $this->addSql('ALTER TABLE site_text DROP intro_fr');
        $this->addSql('ALTER TABLE site_text DROP meta_description_fr');
        $this->addSql('ALTER TABLE site_text DROP search_title_fr');
        $this->addSql('ALTER TABLE site_text DROP about_title_fr');
        $this->addSql('ALTER TABLE site_text DROP about_text_fr');
        $this->addSql('ALTER TABLE site_text DROP gallery_title_fr');
        $this->addSql('ALTER TABLE site_text DROP announcement');
        $this->addSql('ALTER TABLE site_text DROP announcement_fr');
        $this->addSql('ALTER TABLE site_text DROP price_note');
        $this->addSql('ALTER TABLE site_text DROP price_note_fr');
    }
}
