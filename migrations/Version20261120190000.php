<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Shipping zones, seeded off';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shipping_zone (code VARCHAR(16) NOT NULL, price INT NOT NULL, free_from INT DEFAULT NULL, enabled BOOLEAN NOT NULL, PRIMARY KEY (code))');
        $this->addSql("INSERT INTO shipping_zone (code, price, free_from, enabled) VALUES ('france', 0, NULL, false), ('eu', 0, NULL, false), ('world', 0, NULL, false) ON CONFLICT DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shipping_zone');
    }
}
