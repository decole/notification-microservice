<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260314154000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Finalize hashed API tokens by removing legacy raw token storage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN token_hash SET NOT NULL');
        $this->addSql('DROP INDEX IF EXISTS UNIQ_USERS_TOKEN');
        $this->addSql('ALTER TABLE users DROP COLUMN token');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Cannot restore removed raw API tokens.');
    }
}
