<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add doctor availability table with default weekday hours for existing doctors';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE app_doctor_availability (id INT AUTO_INCREMENT NOT NULL, doctor_id INT NOT NULL, day_of_week SMALLINT NOT NULL, start_time TIME NOT NULL COMMENT '(DC2Type:time_immutable)', end_time TIME NOT NULL COMMENT '(DC2Type:time_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_doctor_availability_doctor_day (doctor_id, day_of_week), INDEX IDX_63FA5D8587A03160 (doctor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE app_doctor_availability ADD CONSTRAINT FK_63FA5D8587A03160 FOREIGN KEY (doctor_id) REFERENCES app_user (id) ON DELETE CASCADE');

        $this->addSql("INSERT INTO app_doctor_availability (doctor_id, day_of_week, start_time, end_time, created_at, updated_at)
            SELECT u.id, d.day_of_week, '09:00:00', '17:00:00', NOW(), NOW()
            FROM app_user u
            JOIN (
                SELECT 1 AS day_of_week UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
            ) d
            WHERE u.role_type = 'doctor'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_doctor_availability DROP FOREIGN KEY FK_63FA5D8587A03160');
        $this->addSql('DROP TABLE app_doctor_availability');
    }
}
