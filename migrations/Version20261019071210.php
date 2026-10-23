<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261019071210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add documentation guides published by the VPSAS office';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE documentation_guide (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INT NOT NULL, updated_by_id INT NOT NULL, INDEX IDX_1411BE82B03A8386 (created_by_id), INDEX IDX_1411BE82896DBBDE (updated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE documentation_guide ADD CONSTRAINT FK_1411BE82B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE documentation_guide ADD CONSTRAINT FK_1411BE82896DBBDE FOREIGN KEY (updated_by_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documentation_guide DROP FOREIGN KEY FK_1411BE82B03A8386');
        $this->addSql('ALTER TABLE documentation_guide DROP FOREIGN KEY FK_1411BE82896DBBDE');
        $this->addSql('DROP TABLE documentation_guide');
    }
}
