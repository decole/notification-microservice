<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260314150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prepare users table for hashed API tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USERS_TOKEN_HASH ON users (token_hash) WHERE token_hash IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USERS_TOKEN_HASH');
        $this->addSql('ALTER TABLE users DROP token_hash');
    }
}
