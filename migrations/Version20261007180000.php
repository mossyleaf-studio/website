<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Any Google font, downloaded and self-hosted, for the headings and the body text';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_text DROP heading_font');
        $this->addSql('ALTER TABLE site_text DROP body_font');
        $this->addSql('ALTER TABLE site_text ADD heading_font_family VARCHAR(60) DEFAULT NULL');
        $this->addSql('ALTER TABLE site_text ADD heading_font_file VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE site_text ADD heading_font_weight INT DEFAULT NULL');
        $this->addSql('ALTER TABLE site_text ADD body_font_family VARCHAR(60) DEFAULT NULL');
        $this->addSql('ALTER TABLE site_text ADD body_font_file VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE site_text ADD body_font_weight INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_text DROP heading_font_family');
        $this->addSql('ALTER TABLE site_text DROP heading_font_file');
        $this->addSql('ALTER TABLE site_text DROP heading_font_weight');
        $this->addSql('ALTER TABLE site_text DROP body_font_family');
        $this->addSql('ALTER TABLE site_text DROP body_font_file');
        $this->addSql('ALTER TABLE site_text DROP body_font_weight');
        $this->addSql('ALTER TABLE site_text ADD heading_font VARCHAR(32) DEFAULT \'gaegu\' NOT NULL');
        $this->addSql('ALTER TABLE site_text ADD body_font VARCHAR(32) DEFAULT \'kalam\' NOT NULL');
    }
}
