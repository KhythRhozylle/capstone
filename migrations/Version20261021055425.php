<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021055425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add VPSAS unavailable dates and private office chat messages.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE office_message (id INT AUTO_INCREMENT NOT NULL, sender_name VARCHAR(180) NOT NULL, body VARCHAR(2000) NOT NULL, created_at DATETIME NOT NULL, sender_id INT NOT NULL, recipient_id INT NOT NULL, INDEX IDX_C2E1533DF624B39DE92F8F788B8E8428 (sender_id, recipient_id, created_at), INDEX IDX_C2E1533DF624B39D (sender_id), INDEX IDX_C2E1533DE92F8F78 (recipient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE office_unavailable (id INT AUTO_INCREMENT NOT NULL, day DATE NOT NULL, unit_id INT NOT NULL, owner_id INT NOT NULL, UNIQUE INDEX UNIQ_52B08907F8BD700DE5A02990 (unit_id, day), INDEX IDX_52B08907F8BD700D (unit_id), INDEX IDX_52B089077E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE office_message ADD CONSTRAINT FK_C2E1533DF624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE office_message ADD CONSTRAINT FK_C2E1533DE92F8F78 FOREIGN KEY (recipient_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE office_unavailable ADD CONSTRAINT FK_52B08907F8BD700D FOREIGN KEY (unit_id) REFERENCES sas_unit (id)');
        $this->addSql('ALTER TABLE office_unavailable ADD CONSTRAINT FK_52B089077E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE office_message DROP FOREIGN KEY FK_C2E1533DF624B39D');
        $this->addSql('ALTER TABLE office_message DROP FOREIGN KEY FK_C2E1533DE92F8F78');
        $this->addSql('ALTER TABLE office_unavailable DROP FOREIGN KEY FK_52B08907F8BD700D');
        $this->addSql('ALTER TABLE office_unavailable DROP FOREIGN KEY FK_52B089077E3C61F9');
        $this->addSql('DROP TABLE office_message');
        $this->addSql('DROP TABLE office_unavailable');
    }
}
