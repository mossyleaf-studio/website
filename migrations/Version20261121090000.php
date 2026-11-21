<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Units each order line really took from the stock';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_order_line ADD taken INT DEFAULT NULL');
        $this->addSql("UPDATE shop_order_line SET taken = quantity FROM shop_order WHERE shop_order.id = shop_order_line.order_id AND shop_order.status IN ('paid', 'shipped')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_order_line DROP taken');
    }
}
