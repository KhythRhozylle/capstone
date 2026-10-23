<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261022151918 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add topic, contact number and accommodation note to appointments.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointment ADD topic VARCHAR(80) DEFAULT NULL, ADD contact_number VARCHAR(32) DEFAULT NULL, ADD accommodation_note LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointment DROP topic, DROP contact_number, DROP accommodation_note');
    }
}
