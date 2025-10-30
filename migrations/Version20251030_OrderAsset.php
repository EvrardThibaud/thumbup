<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251030_OrderAsset extends AbstractMigration
{
    public function getDescription(): string { return 'OrderAsset table for delivered thumbnails'; }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform === 'sqlite') {
            $this->addSql('CREATE TABLE order_asset (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                order_id INTEGER NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_size INTEGER NOT NULL,
                mime_type VARCHAR(180) DEFAULT NULL,
                created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
                , updated_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
                , CONSTRAINT FK_ORDER_ASSET_ORDER FOREIGN KEY (order_id) REFERENCES "order"(id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )');
            $this->addSql('CREATE INDEX IDX_ORDER_ASSET_ORDER ON order_asset (order_id)');
        } else {
            $this->addSql('CREATE TABLE order_asset (
                id INT AUTO_INCREMENT NOT NULL,
                order_id INT NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_size INT NOT NULL,
                mime_type VARCHAR(180) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                INDEX IDX_ORDER_ASSET_ORDER (order_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET UTF8MB4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE order_asset ADD CONSTRAINT FK_ORDER_ASSET_ORDER FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE order_asset'); }
}
