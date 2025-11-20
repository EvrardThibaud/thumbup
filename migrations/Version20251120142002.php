<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251120142002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE TABLE email_verification_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, token VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , used_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_C4995C67A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C4995C675F37A13B ON email_verification_token (token)');
        $this->addSql('CREATE INDEX IDX_C4995C67A76ED395 ON email_verification_token (user_id)');
        $this->addSql('CREATE TABLE invitation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER NOT NULL, token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , expires_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , used_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , email VARCHAR(180) DEFAULT NULL, CONSTRAINT FK_F11D61A219EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_F11D61A219EB6921 ON invitation (client_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_invitation_token ON invitation (token)');
        $this->addSql('CREATE TABLE "order" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER NOT NULL, title VARCHAR(255) NOT NULL, brief CLOB DEFAULT NULL, price INTEGER NOT NULL, status VARCHAR(255) NOT NULL, due_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , paid BOOLEAN DEFAULT 0, CONSTRAINT FK_F529939819EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_F529939819EB6921 ON "order" (client_id)');
        $this->addSql('CREATE TABLE order_asset (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, order_id INTEGER NOT NULL, file_name VARCHAR(255) DEFAULT NULL, file_size INTEGER DEFAULT NULL, mime_type VARCHAR(190) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_81312EA18D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_81312EA18D9F6D38 ON order_asset (order_id)');
        $this->addSql('CREATE TABLE password_reset_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, token VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , used_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_6B7BA4B6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6B7BA4B65F37A13B ON password_reset_token (token)');
        $this->addSql('CREATE INDEX IDX_6B7BA4B6A76ED395 ON password_reset_token (user_id)');
        $this->addSql('CREATE TABLE payment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, client_id INTEGER DEFAULT NULL, payment_method VARCHAR(20) NOT NULL, paypal_order_id VARCHAR(64) NOT NULL, paypal_capture_id VARCHAR(64) DEFAULT NULL, status VARCHAR(24) NOT NULL, amount_cents INTEGER NOT NULL, currency VARCHAR(8) NOT NULL, orders_csv CLOB DEFAULT NULL, raw_payload CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_6D28840DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6D28840D19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6D28840DA76ED395 ON payment (user_id)');
        $this->addSql('CREATE INDEX IDX_6D28840D19EB6921 ON payment (client_id)');
        $this->addSql('CREATE TABLE thumbnail (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, order_id INTEGER NOT NULL, file_name VARCHAR(255) DEFAULT NULL, file_size INTEGER DEFAULT NULL, mime_type VARCHAR(190) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_C35726E68D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C35726E68D9F6D38 ON thumbnail (order_id)');
        $this->addSql('CREATE TABLE time_entry (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, related_order_id INTEGER NOT NULL, minutes INTEGER NOT NULL, note VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_6E537C0C2B1C2395 FOREIGN KEY (related_order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6E537C0C2B1C2395 ON time_entry (related_order_id)');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , email VARCHAR(180) NOT NULL, is_verified BOOLEAN NOT NULL, timezone VARCHAR(64) NOT NULL, roles CLOB NOT NULL --(DC2Type:json)
        , password VARCHAR(255) NOT NULL, CONSTRAINT FK_8D93D64919EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64919EB6921 ON user (client_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON user (email)');
        $this->addSql('CREATE TABLE youtube_channel (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, position SMALLINT NOT NULL, CONSTRAINT FK_223DEA319EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_223DEA319EB6921 ON youtube_channel (client_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_client_position ON youtube_channel (client_id, position)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , available_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , delivered_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE email_verification_token');
        $this->addSql('DROP TABLE invitation');
        $this->addSql('DROP TABLE "order"');
        $this->addSql('DROP TABLE order_asset');
        $this->addSql('DROP TABLE password_reset_token');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE thumbnail');
        $this->addSql('DROP TABLE time_entry');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE youtube_channel');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
