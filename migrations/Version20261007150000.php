<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fonts of the headings and of the body text';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_text ADD heading_font VARCHAR(32) DEFAULT \'gaegu\' NOT NULL');
        $this->addSql('ALTER TABLE site_text ADD body_font VARCHAR(32) DEFAULT \'kalam\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_text DROP heading_font');
        $this->addSql('ALTER TABLE site_text DROP body_font');
    }
}
