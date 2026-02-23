<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260102000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'HireIQ initial schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
        $this->addSql('CREATE TABLE workspaces (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL UNIQUE, created_at TIMESTAMP NOT NULL DEFAULT NOW())');
        $this->addSql('CREATE TABLE job_positions (id SERIAL PRIMARY KEY, workspace_id INT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE, title VARCHAR(255) NOT NULL, description TEXT NOT NULL, requirements TEXT, status VARCHAR(50) NOT NULL DEFAULT \'open\', created_at TIMESTAMP NOT NULL DEFAULT NOW())');
        $this->addSql('CREATE TABLE candidates (id SERIAL PRIMARY KEY, workspace_id INT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE, job_position_id INT REFERENCES job_positions(id) ON DELETE SET NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255), status VARCHAR(50) NOT NULL DEFAULT \'new\', ai_score FLOAT, ai_summary TEXT, ai_extracted_data JSONB, created_at TIMESTAMP NOT NULL DEFAULT NOW())');
        $this->addSql('CREATE TABLE documents (id SERIAL PRIMARY KEY, workspace_id INT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE, candidate_id INT REFERENCES candidates(id) ON DELETE CASCADE, type VARCHAR(50) NOT NULL DEFAULT \'cv\', filename VARCHAR(255) NOT NULL, s3_path VARCHAR(500) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT \'uploaded\', mime_type VARCHAR(100), file_size INT, created_at TIMESTAMP NOT NULL DEFAULT NOW())');
        $this->addSql('CREATE TABLE document_chunks (id SERIAL PRIMARY KEY, document_id INT NOT NULL REFERENCES documents(id) ON DELETE CASCADE, content TEXT NOT NULL, embedding vector(1536), page_number INT, chunk_index INT NOT NULL DEFAULT 0, token_count INT, created_at TIMESTAMP NOT NULL DEFAULT NOW())');
        $this->addSql('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
        $this->addSql('CREATE TABLE conversations (id SERIAL PRIMARY KEY, workspace_id INT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE, candidate_id INT REFERENCES candidates(id) ON DELETE CASCADE, title VARCHAR(255), context JSONB, created_at TIMESTAMP NOT NULL DEFAULT NOW())');
        $this->addSql('CREATE TABLE messages (id SERIAL PRIMARY KEY, conversation_id INT NOT NULL REFERENCES conversations(id) ON DELETE CASCADE, role VARCHAR(20) NOT NULL DEFAULT \'user\', content TEXT NOT NULL, sources JSONB, created_at TIMESTAMP NOT NULL DEFAULT NOW())');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messages');
        $this->addSql('DROP TABLE IF EXISTS conversations');
        $this->addSql('DROP TABLE IF EXISTS document_chunks');
        $this->addSql('DROP TABLE IF EXISTS documents');
        $this->addSql('DROP TABLE IF EXISTS candidates');
        $this->addSql('DROP TABLE IF EXISTS job_positions');
        $this->addSql('DROP TABLE IF EXISTS workspaces');
    }
}
