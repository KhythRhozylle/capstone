<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261020081540 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE feedback (id INT AUTO_INCREMENT NOT NULL, kind VARCHAR(255) NOT NULL, rating SMALLINT NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, student_id INT NOT NULL, unit_id INT NOT NULL, appointment_id INT DEFAULT NULL, service_request_id INT DEFAULT NULL, document_request_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_D2294458E5B533F9 (appointment_id), UNIQUE INDEX UNIQ_D2294458D42F8111 (service_request_id), UNIQUE INDEX UNIQ_D2294458E3BD13F3 (document_request_id), INDEX IDX_D2294458CB944F1A (student_id), INDEX IDX_D2294458F8BD700D (unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE office_hour (id INT AUTO_INCREMENT NOT NULL, day_of_week SMALLINT NOT NULL, opens_at TIME NOT NULL, closes_at TIME NOT NULL, unit_id INT NOT NULL, INDEX IDX_9DF1BA42F8BD700D (unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458CB944F1A FOREIGN KEY (student_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458F8BD700D FOREIGN KEY (unit_id) REFERENCES sas_unit (id)');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458E5B533F9 FOREIGN KEY (appointment_id) REFERENCES appointment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458D42F8111 FOREIGN KEY (service_request_id) REFERENCES service_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458E3BD13F3 FOREIGN KEY (document_request_id) REFERENCES document_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE office_hour ADD CONSTRAINT FK_9DF1BA42F8BD700D FOREIGN KEY (unit_id) REFERENCES sas_unit (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D2294458CB944F1A');
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D2294458F8BD700D');
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D2294458E5B533F9');
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D2294458D42F8111');
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D2294458E3BD13F3');
        $this->addSql('ALTER TABLE office_hour DROP FOREIGN KEY FK_9DF1BA42F8BD700D');
        $this->addSql('DROP TABLE feedback');
        $this->addSql('DROP TABLE office_hour');
    }
}
