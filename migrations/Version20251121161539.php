<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251121161539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__youtube_channel AS SELECT id, client_id, name, url, position FROM youtube_channel');
        $this->addSql('DROP TABLE youtube_channel');
        $this->addSql('CREATE TABLE youtube_channel (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER NOT NULL, name VARCHAR(255) DEFAULT NULL, url VARCHAR(255) NOT NULL, position SMALLINT NOT NULL, CONSTRAINT FK_223DEA319EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO youtube_channel (id, client_id, name, url, position) SELECT id, client_id, name, url, position FROM __temp__youtube_channel');
        $this->addSql('DROP TABLE __temp__youtube_channel');
        $this->addSql('CREATE UNIQUE INDEX uniq_client_position ON youtube_channel (client_id, position)');
        $this->addSql('CREATE INDEX IDX_223DEA319EB6921 ON youtube_channel (client_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__youtube_channel AS SELECT id, client_id, name, url, position FROM youtube_channel');
        $this->addSql('DROP TABLE youtube_channel');
        $this->addSql('CREATE TABLE youtube_channel (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, position SMALLINT NOT NULL, CONSTRAINT FK_223DEA319EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO youtube_channel (id, client_id, name, url, position) SELECT id, client_id, name, url, position FROM __temp__youtube_channel');
        $this->addSql('DROP TABLE __temp__youtube_channel');
        $this->addSql('CREATE INDEX IDX_223DEA319EB6921 ON youtube_channel (client_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_client_position ON youtube_channel (client_id, position)');
    }
}
