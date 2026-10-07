<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007120701 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optional logo shown in the header of the site';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_text ADD logo_file VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE site_text ADD logo_width INT DEFAULT NULL');
        $this->addSql('ALTER TABLE site_text ADD logo_height INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_text DROP logo_file');
        $this->addSql('ALTER TABLE site_text DROP logo_width');
        $this->addSql('ALTER TABLE site_text DROP logo_height');
    }
}
