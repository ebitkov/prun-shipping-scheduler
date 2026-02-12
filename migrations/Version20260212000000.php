<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app_user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, fio_username VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, fio_api_key VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E98758AF7E ON app_user (fio_username)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_user');
    }
}
