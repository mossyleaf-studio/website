<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120110939 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalogue pushed by mossytrunk: categories, products and their variants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category (id UUID NOT NULL, name VARCHAR(100) NOT NULL, position INT NOT NULL, label_en TEXT NOT NULL, label_fr TEXT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64C19C15E237E06 ON category (name)');
        $this->addSql('CREATE TABLE product (id UUID NOT NULL, source_id UUID NOT NULL, reference VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, display_name VARCHAR(356) NOT NULL, price INT NOT NULL, retired_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, slug VARCHAR(80) NOT NULL, visible BOOLEAN NOT NULL, position INT NOT NULL, title_en TEXT NOT NULL, title_fr TEXT NOT NULL, description_en TEXT NOT NULL, description_fr TEXT NOT NULL, category_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04AD953C1C61 ON product (source_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04AD989D9B62 ON product (slug)');
        $this->addSql('CREATE INDEX IDX_D34A04AD12469DE2 ON product (category_id)');
        $this->addSql('CREATE TABLE product_variant (id UUID NOT NULL, sku VARCHAR(320) NOT NULL, name VARCHAR(255) DEFAULT NULL, stock INT NOT NULL, position INT NOT NULL, product_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_209AA41DF9038C4 ON product_variant (sku)');
        $this->addSql('CREATE INDEX IDX_209AA41D4584665A ON product_variant (product_id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT FK_209AA41D4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP CONSTRAINT FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE product_variant DROP CONSTRAINT FK_209AA41D4584665A');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_variant');
    }
}
