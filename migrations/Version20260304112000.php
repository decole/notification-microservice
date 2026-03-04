<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260304112000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users/topics/messages/user_topic_read tables and base indexes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id SERIAL NOT NULL, token VARCHAR(255) NOT NULL, username VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW() NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USERS_TOKEN ON users (token)');

        $this->addSql('CREATE TABLE topics (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW() NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TOPICS_NAME ON topics (name)');

        $this->addSql('CREATE TABLE messages (id BIGSERIAL NOT NULL, topic_id INT NOT NULL, user_id INT DEFAULT NULL, content TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW() NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_MESSAGES_TOPIC ON messages (topic_id)');
        $this->addSql('CREATE INDEX IDX_MESSAGES_TOPIC_ID ON messages (topic_id, id)');
        $this->addSql('CREATE INDEX IDX_MESSAGES_USER ON messages (user_id)');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_MESSAGES_TOPIC FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_MESSAGES_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE user_topic_read (user_id INT NOT NULL, topic_id INT NOT NULL, last_read_message_id BIGINT DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW() NOT NULL, PRIMARY KEY(user_id, topic_id))');
        $this->addSql('CREATE INDEX IDX_USER_TOPIC_READ_USER_TOPIC ON user_topic_read (user_id, topic_id)');
        $this->addSql('CREATE INDEX IDX_USER_TOPIC_READ_LAST_MESSAGE ON user_topic_read (last_read_message_id)');
        $this->addSql('ALTER TABLE user_topic_read ADD CONSTRAINT FK_USER_TOPIC_READ_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_topic_read ADD CONSTRAINT FK_USER_TOPIC_READ_TOPIC FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_topic_read ADD CONSTRAINT FK_USER_TOPIC_READ_MESSAGE FOREIGN KEY (last_read_message_id) REFERENCES messages (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("INSERT INTO topics(name, created_at) VALUES('default', NOW()) ON CONFLICT (name) DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_topic_read');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE topics');
        $this->addSql('DROP TABLE users');
    }
}
