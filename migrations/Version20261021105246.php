<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021105246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow multiple unavailable time ranges per office day, preserving existing whole-day blocks.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_52B08907F8BD700DE5A02990 ON office_unavailable');
        $this->addSql('ALTER TABLE office_unavailable ADD start_minute INT DEFAULT 0 NOT NULL, ADD end_minute INT DEFAULT 1440 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_52B08907F8BD700DE5A02990A40D3C251ED9C07D ON office_unavailable (unit_id, day, start_minute, end_minute)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_52B08907F8BD700DE5A02990A40D3C251ED9C07D ON office_unavailable');
        $this->addSql('ALTER TABLE office_unavailable DROP start_minute, DROP end_minute');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_52B08907F8BD700DE5A02990 ON office_unavailable (unit_id, day)');
    }
}
