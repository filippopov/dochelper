<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add first_name and last_name columns to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE app_user ADD first_name VARCHAR(80) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE app_user ADD last_name VARCHAR(80) NOT NULL DEFAULT ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP first_name');
        $this->addSql('ALTER TABLE app_user DROP last_name');
    }
}
