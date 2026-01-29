<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260129105335 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add summary to feature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feature ADD summary VARCHAR(255) NOT NULL DEFAULT \'\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feature DROP summary');
    }
}
