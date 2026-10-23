<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261022062115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store per-user chat archive preferences without changing messages.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat_thread_preference (id INT AUTO_INCREMENT NOT NULL, archived TINYINT NOT NULL, owner_id INT NOT NULL, contact_id INT NOT NULL, UNIQUE INDEX UNIQ_7A11F7027E3C61F9E7A1254A (owner_id, contact_id), INDEX IDX_7A11F7027E3C61F9 (owner_id), INDEX IDX_7A11F702E7A1254A (contact_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE chat_thread_preference ADD CONSTRAINT FK_7A11F7027E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_thread_preference ADD CONSTRAINT FK_7A11F702E7A1254A FOREIGN KEY (contact_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_thread_preference DROP FOREIGN KEY FK_7A11F7027E3C61F9');
        $this->addSql('ALTER TABLE chat_thread_preference DROP FOREIGN KEY FK_7A11F702E7A1254A');
        $this->addSql('DROP TABLE chat_thread_preference');
    }
}
