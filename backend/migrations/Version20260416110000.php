<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add doctor date-specific availability override table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE app_doctor_availability_override (id INT AUTO_INCREMENT NOT NULL, doctor_id INT NOT NULL, date DATE NOT NULL COMMENT '(DC2Type:date_immutable)', start_time TIME NOT NULL COMMENT '(DC2Type:time_immutable)', end_time TIME NOT NULL COMMENT '(DC2Type:time_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_doctor_availability_override_doctor_date (doctor_id, date), INDEX IDX_DA0CBA1887A03160 (doctor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE app_doctor_availability_override ADD CONSTRAINT FK_DA0CBA1887A03160 FOREIGN KEY (doctor_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_doctor_availability_override DROP FOREIGN KEY FK_DA0CBA1887A03160');
        $this->addSql('DROP TABLE app_doctor_availability_override');
    }
}
