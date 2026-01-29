<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260129202440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make summary a text instead of a limited string';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feature ALTER summary TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feature ALTER summary TYPE VARCHAR(255)');
    }
}
