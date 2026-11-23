<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Collections pushed by mossytrunk';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE collection (id UUID NOT NULL, source_id UUID NOT NULL, name VARCHAR(255) NOT NULL, shown BOOLEAN NOT NULL, position INT NOT NULL, label_en TEXT NOT NULL, label_fr TEXT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FC4D6532953C1C61 ON collection (source_id)');
        $this->addSql('ALTER TABLE product ADD collection_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD514956FD FOREIGN KEY (collection_id) REFERENCES collection (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_D34A04AD514956FD ON product (collection_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP CONSTRAINT FK_D34A04AD514956FD');
        $this->addSql('DROP INDEX IDX_D34A04AD514956FD');
        $this->addSql('ALTER TABLE product DROP collection_id');
        $this->addSql('DROP TABLE collection');
    }
}
