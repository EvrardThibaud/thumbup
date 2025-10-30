<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251030140819 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SQLite-safe: add paid (nullable with default 0) and remap statuses.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'sqlite') {
            // 1) Ajouter la colonne en autorisant NULL + default (SQLite accepte INTEGER pour booleans)
            $this->addSql('ALTER TABLE "order" ADD COLUMN paid INTEGER DEFAULT 0');

            // 2) Remapper les anciens statuts
            $this->addSql("UPDATE \"order\" SET paid = 1 WHERE status = 'paid'");
            $this->addSql("UPDATE \"order\" SET status = 'delivered' WHERE status = 'paid'");
            $this->addSql("UPDATE \"order\" SET status = 'accepted'  WHERE status = 'todo'");

            // 3) Normaliser
            $this->addSql('UPDATE "order" SET paid = 0 WHERE paid IS NULL');
        } else {
            // Adapté MySQL/PostgreSQL (nom de table sans guillemets)
            $this->addSql('ALTER TABLE orders ADD paid BOOLEAN NOT NULL DEFAULT FALSE');
            $this->addSql("UPDATE orders SET paid = TRUE, status = 'delivered' WHERE status = 'paid'");
            $this->addSql("UPDATE orders SET status = 'accepted' WHERE status = 'todo'");
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'sqlite') {
            $this->addSql("UPDATE \"order\" SET status = 'todo' WHERE status = 'accepted'");
            $this->addSql("UPDATE \"order\" SET status = 'paid' WHERE status = 'delivered' AND paid = 1");
            // On laisse la colonne paid (DROP COLUMN compliqué sur SQLite)
        } else {
            $this->addSql("UPDATE orders SET status = 'todo' WHERE status = 'accepted'");
            $this->addSql("UPDATE orders SET status = 'paid' WHERE status = 'delivered' AND paid = TRUE");
            // Optionnel: $this->addSql('ALTER TABLE orders DROP COLUMN paid');
        }
    }
}
