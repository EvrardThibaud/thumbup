<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251031_AddUserCreatedAt extends AbstractMigration
{
    public function getDescription(): string { return 'Add createdAt on user (SQLite-safe)'; }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'sqlite') {
            // 1) Default constant obligatoire en SQLite
            $this->addSql("ALTER TABLE user ADD COLUMN created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00'");
            // 2) Mettre à jour les lignes existantes à maintenant
            $this->addSql("UPDATE user SET created_at = datetime('now') WHERE created_at = '1970-01-01 00:00:00'");
            // On laisse le default constant (SQLite ne permet pas facilement de le modifier/retirer)
        } else {
            // MySQL/MariaDB/PostgreSQL
            $this->addSql("ALTER TABLE user ADD created_at DATETIME NOT NULL");
            $this->addSql("UPDATE user SET created_at = NOW() WHERE created_at IS NULL");
            // (optionnel) définir un défaut CURRENT_TIMESTAMP si tu veux
            // $this->addSql("ALTER TABLE user ALTER created_at SET DEFAULT CURRENT_TIMESTAMP");
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'sqlite') {
            // Down complexe (rebuild) — on omet pour simplicité
        } else {
            $this->addSql("ALTER TABLE user DROP created_at");
        }
    }
}
