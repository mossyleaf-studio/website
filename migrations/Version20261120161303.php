<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120161303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Legal pages (draft) and conventions (live)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE convention (id UUID NOT NULL, name VARCHAR(120) NOT NULL, place VARCHAR(120) NOT NULL, starts_on DATE NOT NULL, ends_on DATE NOT NULL, url VARCHAR(500) DEFAULT NULL, note_en TEXT NOT NULL, note_fr TEXT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE legal_page (kind VARCHAR(32) NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, text_en TEXT NOT NULL, text_fr TEXT NOT NULL, PRIMARY KEY (kind))');
        $this->addSql("INSERT INTO legal_page (kind, text_en, text_fr) VALUES ('terms', '', ''), ('legal-notice', '', ''), ('privacy', '', ''), ('shipping-returns', '', '') ON CONFLICT DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE convention');
        $this->addSql('DROP TABLE legal_page');
    }
}
