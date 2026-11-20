<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120152539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Photos of the products, full size and 600 px thumb';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_photo (id UUID NOT NULL, file VARCHAR(64) NOT NULL, width INT NOT NULL, height INT NOT NULL, thumb_file VARCHAR(64) NOT NULL, thumb_width INT NOT NULL, thumb_height INT NOT NULL, position INT NOT NULL, product_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B5EBFF448C9F3610 ON product_photo (file)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B5EBFF44A7E6C3BE ON product_photo (thumb_file)');
        $this->addSql('CREATE INDEX IDX_B5EBFF444584665A ON product_photo (product_id)');
        $this->addSql('ALTER TABLE product_photo ADD CONSTRAINT FK_B5EBFF444584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_photo DROP CONSTRAINT FK_B5EBFF444584665A');
        $this->addSql('DROP TABLE product_photo');
    }
}
