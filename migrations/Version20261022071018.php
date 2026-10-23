<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261022071018 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store private chat file attachments with original names and sizes.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE office_message ADD attachment_filename VARCHAR(80) DEFAULT NULL, ADD attachment_name VARCHAR(255) DEFAULT NULL, ADD attachment_size INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE office_message DROP attachment_filename, DROP attachment_name, DROP attachment_size');
    }
}
