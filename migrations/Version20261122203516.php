<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261122203516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial monStudio schema: users, studios, disciplines, activities, sessions, bookings.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, duration_minutes SMALLINT NOT NULL, default_capacity SMALLINT NOT NULL, level VARCHAR(20) NOT NULL, studio_id INT NOT NULL, discipline_id INT NOT NULL, INDEX IDX_AC74095A446F285F (studio_id), INDEX IDX_AC74095AA5522701 (discipline_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, cancelled_at DATETIME DEFAULT NULL, checked_in_at DATETIME DEFAULT NULL, member_id INT NOT NULL, session_id INT NOT NULL, INDEX idx_booking_session_status (session_id, status), UNIQUE INDEX uniq_booking_member_session (member_id, session_id), INDEX IDX_E00CEDDE7597D3FE (member_id), INDEX IDX_E00CEDDE613FECDF (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE class_session (id INT AUTO_INCREMENT NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, capacity SMALLINT NOT NULL, instructor_name VARCHAR(120) DEFAULT NULL, cancelled TINYINT NOT NULL, activity_id INT NOT NULL, INDEX idx_session_starts_at (starts_at), INDEX IDX_1531B22881C06096 (activity_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE discipline (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, slug VARCHAR(100) NOT NULL, icon VARCHAR(50) NOT NULL, UNIQUE INDEX uniq_discipline_slug (slug), UNIQUE INDEX uniq_discipline_name (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE studio (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL, description LONGTEXT DEFAULT NULL, address VARCHAR(255) NOT NULL, postal_code VARCHAR(10) NOT NULL, city VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, cancellation_deadline_hours SMALLINT NOT NULL, published TINYINT NOT NULL, created_at DATETIME NOT NULL, owner_id INT NOT NULL, INDEX idx_studio_city (city), UNIQUE INDEX uniq_studio_slug (slug), INDEX IDX_4A2B07B67E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE studio_discipline (studio_id INT NOT NULL, discipline_id INT NOT NULL, INDEX IDX_FBEE73A5446F285F (studio_id), INDEX IDX_FBEE73A5A5522701 (discipline_id), PRIMARY KEY (studio_id, discipline_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(80) NOT NULL, last_name VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A446F285F FOREIGN KEY (studio_id) REFERENCES studio (id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AA5522701 FOREIGN KEY (discipline_id) REFERENCES discipline (id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE7597D3FE FOREIGN KEY (member_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE613FECDF FOREIGN KEY (session_id) REFERENCES class_session (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE class_session ADD CONSTRAINT FK_1531B22881C06096 FOREIGN KEY (activity_id) REFERENCES activity (id)');
        $this->addSql('ALTER TABLE studio ADD CONSTRAINT FK_4A2B07B67E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE studio_discipline ADD CONSTRAINT FK_FBEE73A5446F285F FOREIGN KEY (studio_id) REFERENCES studio (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE studio_discipline ADD CONSTRAINT FK_FBEE73A5A5522701 FOREIGN KEY (discipline_id) REFERENCES discipline (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A446F285F');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095AA5522701');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE7597D3FE');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE613FECDF');
        $this->addSql('ALTER TABLE class_session DROP FOREIGN KEY FK_1531B22881C06096');
        $this->addSql('ALTER TABLE studio DROP FOREIGN KEY FK_4A2B07B67E3C61F9');
        $this->addSql('ALTER TABLE studio_discipline DROP FOREIGN KEY FK_FBEE73A5446F285F');
        $this->addSql('ALTER TABLE studio_discipline DROP FOREIGN KEY FK_FBEE73A5A5522701');
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE class_session');
        $this->addSql('DROP TABLE discipline');
        $this->addSql('DROP TABLE studio');
        $this->addSql('DROP TABLE studio_discipline');
        $this->addSql('DROP TABLE `user`');
    }
}
