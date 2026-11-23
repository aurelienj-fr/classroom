<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123133410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop class_session.ends_at: the end time is computed from the activity duration.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE class_session DROP ends_at');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        // Restore the denormalized column from the activity duration before enforcing NOT NULL.
        $this->addSql('ALTER TABLE class_session ADD ends_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE class_session cs INNER JOIN activity a ON a.id = cs.activity_id SET cs.ends_at = DATE_ADD(cs.starts_at, INTERVAL a.duration_minutes MINUTE)');
        $this->addSql('ALTER TABLE class_session MODIFY ends_at DATETIME NOT NULL');
    }
}
