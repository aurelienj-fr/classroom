<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123141930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Owner requests validated by an administrator (status and dates on user).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD owner_request_status VARCHAR(20) DEFAULT NULL, ADD owner_requested_at DATETIME DEFAULT NULL, ADD owner_reviewed_at DATETIME DEFAULT NULL');
        // Owners created before this workflow are considered approved.
        $this->addSql("UPDATE `user` SET owner_request_status = 'approved', owner_reviewed_at = created_at WHERE JSON_CONTAINS(roles, '\"ROLE_OWNER\"')");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` DROP owner_request_status, DROP owner_requested_at, DROP owner_reviewed_at');
    }
}
