<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop target-side uniqueness for domain associations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS UNIQ_DOMAIN_ASSOCIATION_TARGET_NAME');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DOMAIN_ASSOCIATION_TARGET_NAME ON domain_association (target_entity_id, target_name)');
    }
}
