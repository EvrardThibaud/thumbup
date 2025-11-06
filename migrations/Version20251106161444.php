<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251106161444 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__payment AS SELECT id, client_id, paypal_order_id, paypal_capture_id, status, amount_cents, currency, orders_csv, raw_payload, created_at FROM payment');
        $this->addSql('DROP TABLE payment');
        $this->addSql('CREATE TABLE payment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER DEFAULT NULL, user_id INTEGER DEFAULT NULL, paypal_order_id VARCHAR(64) NOT NULL, paypal_capture_id VARCHAR(64) DEFAULT NULL, status VARCHAR(24) NOT NULL, amount_cents INTEGER NOT NULL, currency VARCHAR(8) NOT NULL, orders_csv CLOB DEFAULT NULL, raw_payload CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , payment_method VARCHAR(20) NOT NULL DEFAULT paypal, CONSTRAINT FK_6D28840D19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6D28840DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO payment (id, client_id, paypal_order_id, paypal_capture_id, status, amount_cents, currency, orders_csv, raw_payload, created_at) SELECT id, client_id, paypal_order_id, paypal_capture_id, status, amount_cents, currency, orders_csv, raw_payload, created_at FROM __temp__payment');
        $this->addSql('DROP TABLE __temp__payment');
        $this->addSql('CREATE INDEX IDX_6D28840D19EB6921 ON payment (client_id)');
        $this->addSql('CREATE INDEX IDX_6D28840DA76ED395 ON payment (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__payment AS SELECT id, client_id, paypal_order_id, paypal_capture_id, status, amount_cents, currency, orders_csv, raw_payload, created_at FROM payment');
        $this->addSql('DROP TABLE payment');
        $this->addSql('CREATE TABLE payment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER NOT NULL, paypal_order_id VARCHAR(64) NOT NULL, paypal_capture_id VARCHAR(64) DEFAULT NULL, status VARCHAR(24) NOT NULL, amount_cents INTEGER NOT NULL, currency VARCHAR(8) NOT NULL, orders_csv CLOB DEFAULT NULL, raw_payload CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_6D28840D19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql("INSERT INTO payment (id, client_id, paypal_order_id, paypal_capture_id, status, amount_cents, currency, orders_csv, raw_payload, created_at, payment_method) SELECT id, client_id, paypal_order_id, paypal_capture_id, status, amount_cents, currency, orders_csv, raw_payload, created_at, 'paypal' FROM __temp__payment");        $this->addSql('DROP TABLE __temp__payment');
        $this->addSql('CREATE INDEX IDX_6D28840D19EB6921 ON payment (client_id)');
    }
}
