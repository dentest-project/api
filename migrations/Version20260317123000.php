<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add normalized domain model aggregate';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE domain_entity (id UUID NOT NULL DEFAULT uuid_generate_v4(), project_id UUID NOT NULL, name VARCHAR(255) NOT NULL, description TEXT NOT NULL DEFAULT \'\', PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DOMAIN_ENTITY_PROJECT ON domain_entity (project_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DOMAIN_ENTITY_PROJECT_NAME ON domain_entity (project_id, name)');
        $this->addSql('ALTER TABLE domain_entity ADD CONSTRAINT FK_DOMAIN_ENTITY_PROJECT FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE domain_property (id UUID NOT NULL DEFAULT uuid_generate_v4(), entity_id UUID NOT NULL, name VARCHAR(255) NOT NULL, description TEXT NOT NULL DEFAULT \'\', position INT NOT NULL, type VARCHAR(30) NOT NULL, nullable BOOLEAN NOT NULL DEFAULT FALSE, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DOMAIN_PROPERTY_ENTITY ON domain_property (entity_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DOMAIN_PROPERTY_ENTITY_NAME ON domain_property (entity_id, name)');
        $this->addSql('ALTER TABLE domain_property ADD CONSTRAINT FK_DOMAIN_PROPERTY_ENTITY FOREIGN KEY (entity_id) REFERENCES domain_entity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE domain_property_constraint (id UUID NOT NULL DEFAULT uuid_generate_v4(), property_id UUID NOT NULL, kind VARCHAR(30) NOT NULL, string_value TEXT DEFAULT NULL, integer_value INT DEFAULT NULL, decimal_value NUMERIC(20, 6) DEFAULT NULL, format VARCHAR(30) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DOMAIN_PROPERTY_CONSTRAINT_PROPERTY ON domain_property_constraint (property_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DOMAIN_PROPERTY_CONSTRAINT_KIND ON domain_property_constraint (property_id, kind)');
        $this->addSql('ALTER TABLE domain_property_constraint ADD CONSTRAINT FK_DOMAIN_PROPERTY_CONSTRAINT_PROPERTY FOREIGN KEY (property_id) REFERENCES domain_property (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE domain_association (id UUID NOT NULL DEFAULT uuid_generate_v4(), source_entity_id UUID NOT NULL, source_name VARCHAR(100) NOT NULL, source_cardinality VARCHAR(30) NOT NULL, source_position INT NOT NULL DEFAULT 0, target_entity_id UUID NOT NULL, target_name VARCHAR(100) NOT NULL, target_cardinality VARCHAR(30) NOT NULL, target_position INT NOT NULL DEFAULT 0, description TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DOMAIN_ASSOCIATION_SOURCE_ENTITY ON domain_association (source_entity_id)');
        $this->addSql('CREATE INDEX IDX_DOMAIN_ASSOCIATION_TARGET_ENTITY ON domain_association (target_entity_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DOMAIN_ASSOCIATION_SOURCE_NAME ON domain_association (source_entity_id, source_name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DOMAIN_ASSOCIATION_TARGET_NAME ON domain_association (target_entity_id, target_name)');
        $this->addSql('ALTER TABLE domain_association ADD CONSTRAINT FK_DOMAIN_ASSOCIATION_SOURCE_ENTITY FOREIGN KEY (source_entity_id) REFERENCES domain_entity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE domain_association ADD CONSTRAINT FK_DOMAIN_ASSOCIATION_TARGET_ENTITY FOREIGN KEY (target_entity_id) REFERENCES domain_entity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE domain_association');
        $this->addSql('DROP TABLE domain_property_constraint');
        $this->addSql('DROP TABLE domain_property');
        $this->addSql('DROP TABLE domain_entity');
    }
}
