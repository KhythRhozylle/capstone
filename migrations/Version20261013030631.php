<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013030631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE announcement (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, body LONGTEXT NOT NULL, scope VARCHAR(255) NOT NULL, published_at DATETIME NOT NULL, campus_id INT DEFAULT NULL, published_by_id INT NOT NULL, INDEX IDX_4DB9D91CAF5D55E1 (campus_id), INDEX IDX_4DB9D91C5B075477 (published_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE appointment (id INT AUTO_INCREMENT NOT NULL, reference_code VARCHAR(32) NOT NULL, mode VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, scheduled_at DATETIME NOT NULL, meeting_url VARCHAR(255) DEFAULT NULL, purpose LONGTEXT NOT NULL, staff_notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, student_id INT NOT NULL, unit_id INT NOT NULL, campus_id INT NOT NULL, rescheduled_from_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_FE38F844D6C838 (reference_code), INDEX IDX_FE38F844CB944F1A (student_id), INDEX IDX_FE38F844F8BD700D (unit_id), INDEX IDX_FE38F844AF5D55E1 (campus_id), INDEX IDX_FE38F844A5B9ED (rescheduled_from_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE campus (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(32) NOT NULL, name VARCHAR(180) NOT NULL, UNIQUE INDEX UNIQ_9D09681177153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE care_case (id INT AUTO_INCREMENT NOT NULL, reference_code VARCHAR(32) NOT NULL, status VARCHAR(255) NOT NULL, reason LONGTEXT NOT NULL, student_visible_summary LONGTEXT DEFAULT NULL, clinical_notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, student_id INT NOT NULL, campus_id INT NOT NULL, counselor_id INT DEFAULT NULL, source_concern_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_5F674803D6C838 (reference_code), INDEX IDX_5F674803CB944F1A (student_id), INDEX IDX_5F674803AF5D55E1 (campus_id), INDEX IDX_5F674803A4C7A03A (counselor_id), INDEX IDX_5F674803C0D9BDF1 (source_concern_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE care_follow_up (id INT AUTO_INCREMENT NOT NULL, due_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, notes LONGTEXT NOT NULL, care_case_id INT NOT NULL, INDEX IDX_CE3A66088F714AC0 (care_case_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE concern (id INT AUTO_INCREMENT NOT NULL, reference_code VARCHAR(32) NOT NULL, type VARCHAR(255) NOT NULL, subject VARCHAR(180) NOT NULL, description LONGTEXT NOT NULL, respondents VARCHAR(180) DEFAULT NULL, status VARCHAR(255) NOT NULL, resolution_notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, student_id INT NOT NULL, campus_id INT NOT NULL, assigned_unit_id INT NOT NULL, referred_to_unit_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_281EFBA8D6C838 (reference_code), INDEX IDX_281EFBA8CB944F1A (student_id), INDEX IDX_281EFBA8AF5D55E1 (campus_id), INDEX IDX_281EFBA8F225C882 (assigned_unit_id), INDEX IDX_281EFBA8A130590B (referred_to_unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document_request (id INT AUTO_INCREMENT NOT NULL, reference_code VARCHAR(32) NOT NULL, verification_token VARCHAR(64) NOT NULL, document_type VARCHAR(255) NOT NULL, purpose LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, released_at DATETIME DEFAULT NULL, student_id INT NOT NULL, unit_id INT NOT NULL, campus_id INT NOT NULL, UNIQUE INDEX UNIQ_9FF82943D6C838 (reference_code), UNIQUE INDEX UNIQ_9FF82943C1CC006B (verification_token), INDEX IDX_9FF82943CB944F1A (student_id), INDEX IDX_9FF82943F8BD700D (unit_id), INDEX IDX_9FF82943AF5D55E1 (campus_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, body LONGTEXT NOT NULL, link VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, recipient_id INT NOT NULL, INDEX IDX_BF5476CAE92F8F78 (recipient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE queue_ticket (id INT AUTO_INCREMENT NOT NULL, display_name VARCHAR(180) NOT NULL, number INT NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, called_at DATETIME DEFAULT NULL, campus_id INT NOT NULL, unit_id INT NOT NULL, student_id INT DEFAULT NULL, INDEX IDX_AD41876AAF5D55E1 (campus_id), INDEX IDX_AD41876AF8BD700D (unit_id), INDEX IDX_AD41876ACB944F1A (student_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sas_directorate (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(16) NOT NULL, name VARCHAR(180) NOT NULL, UNIQUE INDEX UNIQ_DC779EC077153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sas_unit (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(32) NOT NULL, name VARCHAR(180) NOT NULL, handles_appointments TINYINT NOT NULL, handles_care TINYINT NOT NULL, handles_grievance TINYINT NOT NULL, handles_documents TINYINT NOT NULL, directorate_id INT NOT NULL, UNIQUE INDEX UNIQ_A036FA7177153098 (code), INDEX IDX_A036FA719BFF530E (directorate_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE service_request (id INT AUTO_INCREMENT NOT NULL, reference_code VARCHAR(32) NOT NULL, subject VARCHAR(180) NOT NULL, description LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, student_id INT NOT NULL, unit_id INT NOT NULL, campus_id INT NOT NULL, UNIQUE INDEX UNIQ_F413DD03D6C838 (reference_code), INDEX IDX_F413DD03CB944F1A (student_id), INDEX IDX_F413DD03F8BD700D (unit_id), INDEX IDX_F413DD03AF5D55E1 (campus_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE status_history (id INT AUTO_INCREMENT NOT NULL, entity_type VARCHAR(64) NOT NULL, entity_id INT NOT NULL, from_status VARCHAR(64) DEFAULT NULL, to_status VARCHAR(64) NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, actor_id INT DEFAULT NULL, INDEX IDX_2F6A07CE10DAF24A (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE student_profile (id INT AUTO_INCREMENT NOT NULL, student_number VARCHAR(32) NOT NULL, program VARCHAR(180) NOT NULL, college VARCHAR(180) NOT NULL, year_level VARCHAR(32) NOT NULL, contact_number VARCHAR(32) NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_6C611FF718A6C7D4 (student_number), UNIQUE INDEX UNIQ_6C611FF7A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(120) NOT NULL, last_name VARCHAR(120) NOT NULL, campus_id INT DEFAULT NULL, staff_unit_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), INDEX IDX_8D93D649AF5D55E1 (campus_id), INDEX IDX_8D93D649A40B67BB (staff_unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE announcement ADD CONSTRAINT FK_4DB9D91CAF5D55E1 FOREIGN KEY (campus_id) REFERENCES campus (id)');
        $this->addSql('ALTER TABLE announcement ADD CONSTRAINT FK_4DB9D91C5B075477 FOREIGN KEY (published_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_FE38F844CB944F1A FOREIGN KEY (student_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_FE38F844F8BD700D FOREIGN KEY (unit_id) REFERENCES sas_unit (id)');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_FE38F844AF5D55E1 FOREIGN KEY (campus_id) REFERENCES campus (id)');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_FE38F844A5B9ED FOREIGN KEY (rescheduled_from_id) REFERENCES appointment (id)');
        $this->addSql('ALTER TABLE care_case ADD CONSTRAINT FK_5F674803CB944F1A FOREIGN KEY (student_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE care_case ADD CONSTRAINT FK_5F674803AF5D55E1 FOREIGN KEY (campus_id) REFERENCES campus (id)');
        $this->addSql('ALTER TABLE care_case ADD CONSTRAINT FK_5F674803A4C7A03A FOREIGN KEY (counselor_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE care_case ADD CONSTRAINT FK_5F674803C0D9BDF1 FOREIGN KEY (source_concern_id) REFERENCES concern (id)');
        $this->addSql('ALTER TABLE care_follow_up ADD CONSTRAINT FK_CE3A66088F714AC0 FOREIGN KEY (care_case_id) REFERENCES care_case (id)');
        $this->addSql('ALTER TABLE concern ADD CONSTRAINT FK_281EFBA8CB944F1A FOREIGN KEY (student_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE concern ADD CONSTRAINT FK_281EFBA8AF5D55E1 FOREIGN KEY (campus_id) REFERENCES campus (id)');
        $this->addSql('ALTER TABLE concern ADD CONSTRAINT FK_281EFBA8F225C882 FOREIGN KEY (assigned_unit_id) REFERENCES sas_unit (id)');
        $this->addSql('ALTER TABLE concern ADD CONSTRAINT FK_281EFBA8A130590B FOREIGN KEY (referred_to_unit_id) REFERENCES sas_unit (id)');
        $this->addSql('ALTER TABLE document_request ADD CONSTRAINT FK_9FF82943CB944F1A FOREIGN KEY (student_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE document_request ADD CONSTRAINT FK_9FF82943F8BD700D FOREIGN KEY (unit_id) REFERENCES sas_unit (id)');
        $this->addSql('ALTER TABLE document_request ADD CONSTRAINT FK_9FF82943AF5D55E1 FOREIGN KEY (campus_id) REFERENCES campus (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAE92F8F78 FOREIGN KEY (recipient_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE queue_ticket ADD CONSTRAINT FK_AD41876AAF5D55E1 FOREIGN KEY (campus_id) REFERENCES campus (id)');
        $this->addSql('ALTER TABLE queue_ticket ADD CONSTRAINT FK_AD41876AF8BD700D FOREIGN KEY (unit_id) REFERENCES sas_unit (id)');
        $this->addSql('ALTER TABLE queue_ticket ADD CONSTRAINT FK_AD41876ACB944F1A FOREIGN KEY (student_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE sas_unit ADD CONSTRAINT FK_A036FA719BFF530E FOREIGN KEY (directorate_id) REFERENCES sas_directorate (id)');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD03CB944F1A FOREIGN KEY (student_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD03F8BD700D FOREIGN KEY (unit_id) REFERENCES sas_unit (id)');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD03AF5D55E1 FOREIGN KEY (campus_id) REFERENCES campus (id)');
        $this->addSql('ALTER TABLE status_history ADD CONSTRAINT FK_2F6A07CE10DAF24A FOREIGN KEY (actor_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE student_profile ADD CONSTRAINT FK_6C611FF7A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649AF5D55E1 FOREIGN KEY (campus_id) REFERENCES campus (id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649A40B67BB FOREIGN KEY (staff_unit_id) REFERENCES sas_unit (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE announcement DROP FOREIGN KEY FK_4DB9D91CAF5D55E1');
        $this->addSql('ALTER TABLE announcement DROP FOREIGN KEY FK_4DB9D91C5B075477');
        $this->addSql('ALTER TABLE appointment DROP FOREIGN KEY FK_FE38F844CB944F1A');
        $this->addSql('ALTER TABLE appointment DROP FOREIGN KEY FK_FE38F844F8BD700D');
        $this->addSql('ALTER TABLE appointment DROP FOREIGN KEY FK_FE38F844AF5D55E1');
        $this->addSql('ALTER TABLE appointment DROP FOREIGN KEY FK_FE38F844A5B9ED');
        $this->addSql('ALTER TABLE care_case DROP FOREIGN KEY FK_5F674803CB944F1A');
        $this->addSql('ALTER TABLE care_case DROP FOREIGN KEY FK_5F674803AF5D55E1');
        $this->addSql('ALTER TABLE care_case DROP FOREIGN KEY FK_5F674803A4C7A03A');
        $this->addSql('ALTER TABLE care_case DROP FOREIGN KEY FK_5F674803C0D9BDF1');
        $this->addSql('ALTER TABLE care_follow_up DROP FOREIGN KEY FK_CE3A66088F714AC0');
        $this->addSql('ALTER TABLE concern DROP FOREIGN KEY FK_281EFBA8CB944F1A');
        $this->addSql('ALTER TABLE concern DROP FOREIGN KEY FK_281EFBA8AF5D55E1');
        $this->addSql('ALTER TABLE concern DROP FOREIGN KEY FK_281EFBA8F225C882');
        $this->addSql('ALTER TABLE concern DROP FOREIGN KEY FK_281EFBA8A130590B');
        $this->addSql('ALTER TABLE document_request DROP FOREIGN KEY FK_9FF82943CB944F1A');
        $this->addSql('ALTER TABLE document_request DROP FOREIGN KEY FK_9FF82943F8BD700D');
        $this->addSql('ALTER TABLE document_request DROP FOREIGN KEY FK_9FF82943AF5D55E1');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAE92F8F78');
        $this->addSql('ALTER TABLE queue_ticket DROP FOREIGN KEY FK_AD41876AAF5D55E1');
        $this->addSql('ALTER TABLE queue_ticket DROP FOREIGN KEY FK_AD41876AF8BD700D');
        $this->addSql('ALTER TABLE queue_ticket DROP FOREIGN KEY FK_AD41876ACB944F1A');
        $this->addSql('ALTER TABLE sas_unit DROP FOREIGN KEY FK_A036FA719BFF530E');
        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD03CB944F1A');
        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD03F8BD700D');
        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD03AF5D55E1');
        $this->addSql('ALTER TABLE status_history DROP FOREIGN KEY FK_2F6A07CE10DAF24A');
        $this->addSql('ALTER TABLE student_profile DROP FOREIGN KEY FK_6C611FF7A76ED395');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649AF5D55E1');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649A40B67BB');
        $this->addSql('DROP TABLE announcement');
        $this->addSql('DROP TABLE appointment');
        $this->addSql('DROP TABLE campus');
        $this->addSql('DROP TABLE care_case');
        $this->addSql('DROP TABLE care_follow_up');
        $this->addSql('DROP TABLE concern');
        $this->addSql('DROP TABLE document_request');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE queue_ticket');
        $this->addSql('DROP TABLE sas_directorate');
        $this->addSql('DROP TABLE sas_unit');
        $this->addSql('DROP TABLE service_request');
        $this->addSql('DROP TABLE status_history');
        $this->addSql('DROP TABLE student_profile');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
