<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Products the studio picks for the home';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD picked BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP picked');
    }
}
