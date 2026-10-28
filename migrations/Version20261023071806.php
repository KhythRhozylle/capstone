<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261023071806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add office-specific document register columns and custom cell values.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE office_document_column (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(80) NOT NULL, unit_id INT NOT NULL, UNIQUE INDEX office_document_column_label (unit_id, label), INDEX IDX_65C92B73F8BD700D (unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE office_document_column ADD CONSTRAINT FK_65C92B73F8BD700D FOREIGN KEY (unit_id) REFERENCES sas_unit (id)');
        $this->addSql('ALTER TABLE office_document_record ADD extra_data JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE office_document_column DROP FOREIGN KEY FK_65C92B73F8BD700D');
        $this->addSql('DROP TABLE office_document_column');
        $this->addSql('ALTER TABLE office_document_record DROP extra_data');
    }
}
