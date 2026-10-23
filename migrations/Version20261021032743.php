<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021032743 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add VPSAS profile photos, per-person activity records and status-history attribution.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE office_activity (id INT AUTO_INCREMENT NOT NULL, account_id INT NOT NULL, profile_key VARCHAR(20) NOT NULL, actor_name VARCHAR(180) NOT NULL, method VARCHAR(12) NOT NULL, path VARCHAR(2048) NOT NULL, route VARCHAR(255) NOT NULL, status_code INT NOT NULL, occurred_at DATETIME NOT NULL, INDEX IDX_34D275859B6B5FBA87C03D1B (account_id, occurred_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE status_history ADD actor_name VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD profile_photos JSON DEFAULT NULL');
        $this->addSql("UPDATE user SET profile_photos = '[]'");
        $this->addSql('ALTER TABLE user MODIFY profile_photos JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE office_activity');
        $this->addSql('ALTER TABLE status_history DROP actor_name');
        $this->addSql('ALTER TABLE `user` DROP profile_photos');
    }
}
