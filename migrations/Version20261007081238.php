<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007081238 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Users, sessions, site texts, links and artworks, with the links of the static site';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (id UUID NOT NULL, account_id VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, display_name VARCHAR(60) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E99B6B5FBA ON app_user (account_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql('CREATE TABLE artwork (id UUID NOT NULL, file VARCHAR(64) NOT NULL, alt VARCHAR(200) NOT NULL, width INT NOT NULL, height INT NOT NULL, featured BOOLEAN NOT NULL, position INT NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_881FC5768C9F3610 ON artwork (file)');
        $this->addSql('CREATE INDEX artwork_featured ON artwork (featured)');
        $this->addSql('CREATE TABLE link (id UUID NOT NULL, title VARCHAR(60) NOT NULL, description VARCHAR(120) NOT NULL, url VARCHAR(500) NOT NULL, tape VARCHAR(16) NOT NULL, position INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE site_text (id INT NOT NULL, studio_name VARCHAR(60) NOT NULL, intro VARCHAR(200) NOT NULL, meta_description VARCHAR(300) NOT NULL, home_note_title VARCHAR(80) NOT NULL, home_note_text VARCHAR(600) NOT NULL, about_title VARCHAR(80) NOT NULL, about_text VARCHAR(2000) NOT NULL, gallery_title VARCHAR(80) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE sessions (sess_id VARCHAR(128) NOT NULL, sess_data BYTEA NOT NULL, sess_lifetime INT NOT NULL, sess_time INT NOT NULL, PRIMARY KEY (sess_id))');
        $this->addSql('CREATE INDEX sess_lifetime_idx ON sessions (sess_lifetime)');
        $this->addSql("INSERT INTO link (id, title, description, url, tape, position) VALUES (gen_random_uuid(), 'Shop on Etsy', 'Prints, stickers and illustrations', 'https://www.etsy.com/shop/mossyleafstudio', 'leaf', 0), (gen_random_uuid(), 'Follow on Instagram', 'New drawings and market dates', 'https://www.instagram.com/mossyleaf.studio/', 'blossom', 1)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE artwork');
        $this->addSql('DROP TABLE link');
        $this->addSql('DROP TABLE site_text');
        $this->addSql('DROP TABLE sessions');
    }
}
