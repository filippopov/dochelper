<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add role type, refresh tokens, and appointments tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE app_user ADD role_type VARCHAR(20) NOT NULL DEFAULT 'patient'");
        $this->addSql("UPDATE app_user SET role_type = 'doctor' WHERE JSON_CONTAINS(roles, '\"ROLE_DOCTOR\"') = 1");
        $this->addSql("UPDATE app_user SET role_type = 'patient' WHERE role_type <> 'doctor'");

        $this->addSql("CREATE TABLE app_refresh_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token_hash VARCHAR(128) NOT NULL, expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', revoked_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_refresh_token_hash (token_hash), INDEX IDX_DFC8A05EA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE app_refresh_token ADD CONSTRAINT FK_DFC8A05EA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');

        $this->addSql("CREATE TABLE app_appointment (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, doctor_id INT NOT NULL, scheduled_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', duration_minutes INT NOT NULL, status VARCHAR(20) NOT NULL, reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', cancelled_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_appointment_patient (patient_id), INDEX idx_appointment_doctor (doctor_id), INDEX idx_appointment_status (status), INDEX IDX_18F1D1A66B899279 (patient_id), INDEX IDX_18F1D1A687A03160 (doctor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE app_appointment ADD CONSTRAINT FK_18F1D1A66B899279 FOREIGN KEY (patient_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_appointment ADD CONSTRAINT FK_18F1D1A687A03160 FOREIGN KEY (doctor_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_refresh_token DROP FOREIGN KEY FK_DFC8A05EA76ED395');
        $this->addSql('ALTER TABLE app_appointment DROP FOREIGN KEY FK_18F1D1A66B899279');
        $this->addSql('ALTER TABLE app_appointment DROP FOREIGN KEY FK_18F1D1A687A03160');
        $this->addSql('DROP TABLE app_refresh_token');
        $this->addSql('DROP TABLE app_appointment');
        $this->addSql('ALTER TABLE app_user DROP role_type');
    }
}
