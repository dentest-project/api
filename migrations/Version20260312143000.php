<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add summary to path';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE path ADD summary TEXT NOT NULL DEFAULT \'\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE path DROP summary');
    }
}
