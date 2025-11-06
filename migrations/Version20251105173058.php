<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251105173058 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE payment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER NOT NULL, paypal_order_id VARCHAR(64) NOT NULL, paypal_capture_id VARCHAR(64) DEFAULT NULL, status VARCHAR(24) NOT NULL, amount_cents INTEGER NOT NULL, currency VARCHAR(8) NOT NULL, orders_csv CLOB DEFAULT NULL, raw_payload CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_6D28840D19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6D28840D19EB6921 ON payment (client_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__order_asset AS SELECT id, order_id, file_name, file_size, mime_type, created_at, updated_at FROM order_asset');
        $this->addSql('DROP TABLE order_asset');
        $this->addSql('CREATE TABLE order_asset (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, order_id INTEGER NOT NULL, file_name VARCHAR(255) DEFAULT NULL, file_size INTEGER DEFAULT NULL, mime_type VARCHAR(190) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_81312EA18D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO order_asset (id, order_id, file_name, file_size, mime_type, created_at, updated_at) SELECT id, order_id, file_name, file_size, mime_type, created_at, updated_at FROM __temp__order_asset');
        $this->addSql('DROP TABLE __temp__order_asset');
        $this->addSql('CREATE INDEX IDX_81312EA18D9F6D38 ON order_asset (order_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__thumbnail AS SELECT id, order_id, file_name, file_size, mime_type, created_at, updated_at FROM thumbnail');
        $this->addSql('DROP TABLE thumbnail');
        $this->addSql('CREATE TABLE thumbnail (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, order_id INTEGER NOT NULL, file_name VARCHAR(255) DEFAULT NULL, file_size INTEGER DEFAULT NULL, mime_type VARCHAR(190) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_C35726E68D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO thumbnail (id, order_id, file_name, file_size, mime_type, created_at, updated_at) SELECT id, order_id, file_name, file_size, mime_type, created_at, updated_at FROM __temp__thumbnail');
        $this->addSql('DROP TABLE __temp__thumbnail');
        $this->addSql('CREATE INDEX IDX_C35726E68D9F6D38 ON thumbnail (order_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE payment');
        $this->addSql('CREATE TEMPORARY TABLE __temp__order_asset AS SELECT id, order_id, file_name, file_size, mime_type, created_at, updated_at FROM order_asset');
        $this->addSql('DROP TABLE order_asset');
        $this->addSql('CREATE TABLE order_asset (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, order_id INTEGER NOT NULL, file_name VARCHAR(255) NOT NULL, file_size INTEGER NOT NULL, mime_type VARCHAR(180) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_81312EA18D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO order_asset (id, order_id, file_name, file_size, mime_type, created_at, updated_at) SELECT id, order_id, file_name, file_size, mime_type, created_at, updated_at FROM __temp__order_asset');
        $this->addSql('DROP TABLE __temp__order_asset');
        $this->addSql('CREATE INDEX IDX_81312EA18D9F6D38 ON order_asset (order_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__thumbnail AS SELECT id, order_id, file_name, file_size, mime_type, created_at, updated_at FROM thumbnail');
        $this->addSql('DROP TABLE thumbnail');
        $this->addSql('CREATE TABLE thumbnail (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, order_id INTEGER NOT NULL, file_name VARCHAR(255) NOT NULL, file_size INTEGER NOT NULL, mime_type VARCHAR(190) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_C35726E68D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO thumbnail (id, order_id, file_name, file_size, mime_type, created_at, updated_at) SELECT id, order_id, file_name, file_size, mime_type, created_at, updated_at FROM __temp__thumbnail');
        $this->addSql('DROP TABLE __temp__thumbnail');
        $this->addSql('CREATE INDEX IDX_C35726E68D9F6D38 ON thumbnail (order_id)');
    }
}
