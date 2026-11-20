<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Orders of the shop and their lines';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_order (id UUID NOT NULL, reference VARCHAR(12) NOT NULL, token VARCHAR(43) NOT NULL, status VARCHAR(16) NOT NULL, test BOOLEAN NOT NULL, locale VARCHAR(2) NOT NULL, email VARCHAR(254) NOT NULL, name VARCHAR(120) NOT NULL, phone VARCHAR(32) DEFAULT NULL, line1 VARCHAR(120) NOT NULL, line2 VARCHAR(120) DEFAULT NULL, postal_code VARCHAR(16) NOT NULL, city VARCHAR(80) NOT NULL, region VARCHAR(80) DEFAULT NULL, country VARCHAR(2) NOT NULL, zone VARCHAR(16) NOT NULL, goods INT NOT NULL, shipping INT NOT NULL, payment_id VARCHAR(255) DEFAULT NULL, payment_url TEXT DEFAULT NULL, transaction_code VARCHAR(64) DEFAULT NULL, tracking_number VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, shipped_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, exported_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_323FC9CAAEA34913 ON shop_order (reference)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_323FC9CA5F37A13B ON shop_order (token)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_323FC9CA4C3A3BB ON shop_order (payment_id)');
        $this->addSql('CREATE TABLE shop_order_line (id UUID NOT NULL, sku VARCHAR(320) NOT NULL, name VARCHAR(356) NOT NULL, title VARCHAR(356) NOT NULL, variant VARCHAR(255) DEFAULT NULL, unit_price INT NOT NULL, quantity INT NOT NULL, position INT NOT NULL, order_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E69663C78D9F6D38 ON shop_order_line (order_id)');
        $this->addSql('ALTER TABLE shop_order_line ADD CONSTRAINT FK_E69663C78D9F6D38 FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shop_order_line');
        $this->addSql('DROP TABLE shop_order');
    }
}
