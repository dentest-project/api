<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add domain fixtures and validated fixture links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE domain_fixture (id UUID NOT NULL DEFAULT uuid_generate_v4(), project_id UUID NOT NULL, entity_id UUID NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DOMAIN_FIXTURE_PROJECT ON domain_fixture (project_id)');
        $this->addSql('CREATE INDEX IDX_DOMAIN_FIXTURE_ENTITY ON domain_fixture (entity_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DOMAIN_FIXTURE_ENTITY_NAME ON domain_fixture (entity_id, name)');
        $this->addSql('ALTER TABLE domain_fixture ADD CONSTRAINT FK_DOMAIN_FIXTURE_PROJECT FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE domain_fixture ADD CONSTRAINT FK_DOMAIN_FIXTURE_ENTITY FOREIGN KEY (entity_id) REFERENCES domain_entity (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE domain_fixture_property_value (id UUID NOT NULL DEFAULT uuid_generate_v4(), fixture_id UUID NOT NULL, property_id UUID NOT NULL, string_value TEXT DEFAULT NULL, integer_value INT DEFAULT NULL, decimal_value TEXT DEFAULT NULL, boolean_value BOOLEAN DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DOMAIN_FIXTURE_PROPERTY_VALUE_FIXTURE ON domain_fixture_property_value (fixture_id)');
        $this->addSql('CREATE INDEX IDX_DOMAIN_FIXTURE_PROPERTY_VALUE_PROPERTY ON domain_fixture_property_value (property_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DOMAIN_FIXTURE_PROPERTY_VALUE ON domain_fixture_property_value (fixture_id, property_id)');
        $this->addSql('ALTER TABLE domain_fixture_property_value ADD CONSTRAINT FK_DOMAIN_FIXTURE_PROPERTY_VALUE_FIXTURE FOREIGN KEY (fixture_id) REFERENCES domain_fixture (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE domain_fixture_property_value ADD CONSTRAINT FK_DOMAIN_FIXTURE_PROPERTY_VALUE_PROPERTY FOREIGN KEY (property_id) REFERENCES domain_property (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE domain_fixture_association_value (id UUID NOT NULL DEFAULT uuid_generate_v4(), fixture_id UUID NOT NULL, association_id UUID NOT NULL, target_fixture_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DOMAIN_FIXTURE_ASSOCIATION_VALUE_FIXTURE ON domain_fixture_association_value (fixture_id)');
        $this->addSql('CREATE INDEX IDX_DOMAIN_FIXTURE_ASSOCIATION_VALUE_ASSOCIATION ON domain_fixture_association_value (association_id)');
        $this->addSql('CREATE INDEX IDX_DOMAIN_FIXTURE_ASSOCIATION_VALUE_TARGET ON domain_fixture_association_value (target_fixture_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DOMAIN_FIXTURE_ASSOCIATION_VALUE ON domain_fixture_association_value (fixture_id, association_id, target_fixture_id)');
        $this->addSql('ALTER TABLE domain_fixture_association_value ADD CONSTRAINT FK_DOMAIN_FIXTURE_ASSOCIATION_VALUE_FIXTURE FOREIGN KEY (fixture_id) REFERENCES domain_fixture (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE domain_fixture_association_value ADD CONSTRAINT FK_DOMAIN_FIXTURE_ASSOCIATION_VALUE_ASSOCIATION FOREIGN KEY (association_id) REFERENCES domain_association (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE domain_fixture_association_value ADD CONSTRAINT FK_DOMAIN_FIXTURE_ASSOCIATION_VALUE_TARGET FOREIGN KEY (target_fixture_id) REFERENCES domain_fixture (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE domain_fixture_association_value');
        $this->addSql('DROP TABLE domain_fixture_property_value');
        $this->addSql('DROP TABLE domain_fixture');
    }
}
