<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007130633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Published snapshot of the site that visitors see';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE published_site (id INT NOT NULL, content TEXT NOT NULL, files JSON NOT NULL, page VARCHAR(16) NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_by VARCHAR(60) NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE published_site');
    }
}
