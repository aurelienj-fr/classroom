<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123084100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add translated discipline names (JSON, indexed by locale).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // MySQL forbids literal defaults on JSON columns: add as nullable, backfill, then enforce NOT NULL.
        $this->addSql('ALTER TABLE discipline ADD name_translations JSON DEFAULT NULL');
        $this->addSql('UPDATE discipline SET name_translations = JSON_OBJECT()');
        $this->addSql('ALTER TABLE discipline MODIFY name_translations JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE discipline DROP name_translations');
    }
}
