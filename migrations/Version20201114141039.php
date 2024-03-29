<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201114141039 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE scenario_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE scenario_step_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE step_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE step_param_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE step_part_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql("CREATE TABLE feature (id UUID NOT NULL DEFAULT uuid_generate_v4(), path_id UUID, title VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, status feature_status NOT NULL DEFAULT 'draft', PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX IDX_1FD77566D96C566B ON feature (path_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1FD77566989D9B62D96C566B ON feature (slug, path_id)');
        $this->addSql('CREATE TABLE inline_step_param (id INT NOT NULL, step_part_id INT DEFAULT NULL, content VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_13CFC238FC1ECD03 ON inline_step_param (step_part_id)');
        $this->addSql('CREATE TABLE multiline_step_param (id INT NOT NULL, content VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE path (id UUID NOT NULL DEFAULT uuid_generate_v4(), parent_id UUID DEFAULT NULL, path VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B548B0F727ACA70 ON path (parent_id)');
        $this->addSql('CREATE TABLE organization (id UUID NOT NULL DEFAULT uuid_generate_v4(), name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C1EE637C989D9B62 ON organization (slug)');
        $this->addSql('CREATE TABLE project (id UUID NOT NULL DEFAULT uuid_generate_v4(), root_path_id UUID, title VARCHAR(255) NOT NULL, visibility project_visibility NOT NULL, organization_id UUID DEFAULT NULL, slug VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE32C8A3DE ON project (organization_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE352D1EA1 ON project (root_path_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE32C8A3DE989D9B62 ON project (organization_id, slug)');
        $this->addSql('CREATE TABLE scenario (id INT NOT NULL, feature_id UUID, type scenario_type NOT NULL, title VARCHAR(255) NOT NULL, examples JSON DEFAULT NULL, priority INT NOT NULL DEFAULT 0, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3E45C8D860E4B879 ON scenario (feature_id)');
        $this->addSql('CREATE TABLE scenario_step (id INT NOT NULL, scenario_id INT DEFAULT NULL, step_id INT DEFAULT NULL, adverb step_adverb NOT NULL, priority INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_23742800E04E49DF ON scenario_step (scenario_id)');
        $this->addSql('CREATE INDEX IDX_2374280073B21E9C ON scenario_step (step_id)');
        $this->addSql('CREATE TABLE step (id INT NOT NULL, type step_type NOT NULL, project_id UUID NOT NULL, extra_param_type step_extra_param_type NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE step_param (id INT NOT NULL, step_id INT DEFAULT NULL, type param_type NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B8D88B7673B21E9C ON step_param (step_id)');
        $this->addSql('CREATE TABLE step_part (id INT NOT NULL, step_id INT DEFAULT NULL, type step_part_type NOT NULL, content VARCHAR(255) NOT NULL, priority INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_799ED9773B21E9C ON step_part (step_id)');
        $this->addSql('CREATE TABLE table_step_param (id INT NOT NULL, content JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE app_user (id UUID NOT NULL, username VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(100) NOT NULL, roles JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN app_user.id IS \'(DC2Type:ulid)\'');
        $this->addSql("CREATE TABLE project_user (project_id UUID NOT NULL, user_id UUID NOT NULL, permissions JSON NOT NULL, token TEXT NOT NULL DEFAULT '', PRIMARY KEY(project_id, user_id))");
        $this->addSql('CREATE INDEX IDX_B4021E51166D1F9C ON project_user (project_id)');
        $this->addSql('CREATE INDEX IDX_B4021E51A76ED395 ON project_user (user_id)');
        $this->addSql('COMMENT ON COLUMN project_user.user_id IS \'(DC2Type:ulid)\'');
        $this->addSql('CREATE TABLE organization_user (organization_id UUID NOT NULL, user_id UUID NOT NULL, permissions JSON NOT NULL, PRIMARY KEY(organization_id, user_id))');
        $this->addSql('CREATE INDEX IDX_B49AE8D432C8A3DE ON organization_user (organization_id)');
        $this->addSql('CREATE INDEX IDX_B49AE8D4A76ED395 ON organization_user (user_id)');
        $this->addSql('COMMENT ON COLUMN organization_user.user_id IS \'(DC2Type:ulid)\'');
        $this->addSql('CREATE INDEX IDX_88BDF3E9F85E0677 ON app_user (username)');
        $this->addSql('CREATE INDEX IDX_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql('CREATE INDEX IDX_C1EE637C989D9B62 ON organization (slug)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE989D9B62 ON project (slug)');
        $this->addSql('CREATE INDEX IDX_B4021E515F37A13B ON project_user (token)');
        $this->addSql('ALTER TABLE feature ADD CONSTRAINT FK_1FD77566D96C566B FOREIGN KEY (path_id) REFERENCES path (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inline_step_param ADD CONSTRAINT FK_13CFC238FC1ECD03 FOREIGN KEY (step_part_id) REFERENCES step_part (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inline_step_param ADD CONSTRAINT FK_13CFC238BF396750 FOREIGN KEY (id) REFERENCES step_param (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE multiline_step_param ADD CONSTRAINT FK_9C00174BF396750 FOREIGN KEY (id) REFERENCES step_param (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE path ADD CONSTRAINT FK_B548B0F727ACA70 FOREIGN KEY (parent_id) REFERENCES path (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE352D1EA1 FOREIGN KEY (root_path_id) REFERENCES path (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON UPDATE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE scenario ADD CONSTRAINT FK_3E45C8D860E4B879 FOREIGN KEY (feature_id) REFERENCES feature (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE scenario_step ADD CONSTRAINT FK_23742800E04E49DF FOREIGN KEY (scenario_id) REFERENCES scenario (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE scenario_step ADD CONSTRAINT FK_2374280073B21E9C FOREIGN KEY (step_id) REFERENCES step (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE step ADD CONSTRAINT FK_43B9FE3C166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE step_param ADD CONSTRAINT FK_B8D88B7673B21E9C FOREIGN KEY (step_id) REFERENCES scenario_step (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE step_part ADD CONSTRAINT FK_799ED9773B21E9C FOREIGN KEY (step_id) REFERENCES step (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE table_step_param ADD CONSTRAINT FK_E536D31BBF396750 FOREIGN KEY (id) REFERENCES step_param (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_user ADD CONSTRAINT FK_B4021E51166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_user ADD CONSTRAINT FK_B4021E51A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D432C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON UPDATE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D4A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE scenario DROP CONSTRAINT FK_3E45C8D860E4B879');
        $this->addSql('ALTER TABLE feature DROP CONSTRAINT FK_1FD77566D96C566B');
        $this->addSql('ALTER TABLE path DROP CONSTRAINT FK_B548B0F727ACA70');
        $this->addSql('ALTER TABLE project DROP CONSTRAINT FK_2FB3D0EE352D1EA1');
        $this->addSql('ALTER TABLE scenario_step DROP CONSTRAINT FK_23742800E04E49DF');
        $this->addSql('ALTER TABLE step_param DROP CONSTRAINT FK_B8D88B7673B21E9C');
        $this->addSql('ALTER TABLE scenario_step DROP CONSTRAINT FK_2374280073B21E9C');
        $this->addSql('ALTER TABLE step_part DROP CONSTRAINT FK_799ED9773B21E9C');
        $this->addSql('ALTER TABLE inline_step_param DROP CONSTRAINT FK_13CFC238BF396750');
        $this->addSql('ALTER TABLE multiline_step_param DROP CONSTRAINT FK_9C00174BF396750');
        $this->addSql('ALTER TABLE table_step_param DROP CONSTRAINT FK_E536D31BBF396750');
        $this->addSql('ALTER TABLE inline_step_param DROP CONSTRAINT FK_13CFC238FC1ECD03');
        $this->addSql('DROP SEQUENCE scenario_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE scenario_step_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE step_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE step_param_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE step_part_id_seq CASCADE');
        $this->addSql('DROP TABLE organization_user');
        $this->addSql('DROP TABLE project_user');
        $this->addSql('DROP TABLE feature');
        $this->addSql('DROP TABLE inline_step_param');
        $this->addSql('DROP TABLE multiline_step_param');
        $this->addSql('DROP TABLE path');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE organization');
        $this->addSql('DROP TABLE scenario');
        $this->addSql('DROP TABLE scenario_step');
        $this->addSql('DROP TABLE step');
        $this->addSql('DROP TABLE step_param');
        $this->addSql('DROP TABLE step_part');
        $this->addSql('DROP TABLE table_step_param');
    }
}
