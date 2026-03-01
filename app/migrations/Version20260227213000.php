<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add text_content column to documents table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documents ADD COLUMN IF NOT EXISTS text_content TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documents DROP COLUMN IF EXISTS text_content');
    }
}
