<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007131304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optional title of the home page in search results';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_text ADD search_title VARCHAR(70) DEFAULT \'\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_text DROP search_title');
    }
}
