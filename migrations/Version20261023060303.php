<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261023060303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add office document register with campus, amount, receipt and release details.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE office_document_record (id INT AUTO_INCREMENT NOT NULL, particular LONGTEXT NOT NULL, amount NUMERIC(12, 2) NOT NULL, received_at DATE NOT NULL, received_by VARCHAR(180) NOT NULL, released_at DATE DEFAULT NULL, released_by VARCHAR(180) DEFAULT NULL, status VARCHAR(255) NOT NULL, unit_id INT NOT NULL, campus_id INT NOT NULL, INDEX IDX_83AE339CF8BD700D (unit_id), INDEX IDX_83AE339CAF5D55E1 (campus_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE office_document_record ADD CONSTRAINT FK_83AE339CF8BD700D FOREIGN KEY (unit_id) REFERENCES sas_unit (id)');
        $this->addSql('ALTER TABLE office_document_record ADD CONSTRAINT FK_83AE339CAF5D55E1 FOREIGN KEY (campus_id) REFERENCES campus (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE office_document_record DROP FOREIGN KEY FK_83AE339CF8BD700D');
        $this->addSql('ALTER TABLE office_document_record DROP FOREIGN KEY FK_83AE339CAF5D55E1');
        $this->addSql('DROP TABLE office_document_record');
    }
}
