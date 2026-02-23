# 🧠 HireIQ — AI-Powered HR Recruitment Platform
## Setup Guide & Project Structure

---

## ⚡ Quick Start (5 steps)

### Step 1 — Clone / create your project folder
```bash
mkdir hireiq && cd hireiq
# Copy all Docker files here (docker-compose.yml, docker/ folder)
```

### Step 2 — Create your Symfony app
```bash
# Create the Symfony app inside an /app subfolder
docker run --rm -v $(pwd)/app:/app composer create-project \
  symfony/skeleton app

cd app

# Install all packages you need for HireIQ
docker run --rm -v $(pwd):/app composer require \
  symfony/orm-pack \
  symfony/messenger \
  symfony/validator \
  symfony/serializer-pack \
  nelmio/api-doc-bundle \
  league/flysystem-bundle \
  league/flysystem-aws-s3-v3 \
  smalot/pdfparser \
  guzzlehttp/guzzle \
  predis/predis
```

### Step 3 — Configure environment
```bash
cp .env.example .env
# Edit .env and add your OpenAI or Anthropic API key
```

### Step 4 — Start Docker
```bash
docker compose up -d
```

### Step 5 — Run migrations
```bash
docker exec hireiq_app php bin/console doctrine:migrations:migrate
```

✅ App running at: http://localhost:8080
✅ MinIO console at: http://localhost:9001 (login: hireiq / hireiq123)

---

## 📁 Project Folder Structure

```
hireiq/
├── docker-compose.yml
├── .env.example
├── docker/
│   ├── php/
│   │   └── Dockerfile
│   ├── nginx/
│   │   └── default.conf
│   └── postgres/
│       └── init.sql          ← enables pgvector
│
└── app/                      ← Your Symfony project lives here
    ├── src/
    │   ├── Controller/
    │   │   ├── JobController.php         ← CRUD for job positions
    │   │   ├── CandidateController.php   ← CV upload + candidate management
    │   │   ├── AnalysisController.php    ← AI ranking + scoring
    │   │   └── ChatController.php        ← Chat with CV (streaming)
    │   │
    │   ├── Entity/
    │   │   ├── Workspace.php             ← Multi-tenant companies
    │   │   ├── JobPosition.php           ← Open roles
    │   │   ├── Candidate.php             ← Applicants
    │   │   ├── Document.php              ← Uploaded files (CV, JD)
    │   │   ├── DocumentChunk.php         ← Text chunks with embeddings (pgvector)
    │   │   ├── Conversation.php          ← Chat sessions
    │   │   └── Message.php               ← Chat messages
    │   │
    │   ├── Service/
    │   │   ├── AI/
    │   │   │   ├── EmbeddingService.php      ← Convert text → vector
    │   │   │   ├── LLMService.php            ← Call OpenAI/Claude API
    │   │   │   ├── RagService.php            ← RAG: search chunks + generate answer
    │   │   │   ├── CandidateRankerService.php ← Score CVs against job description
    │   │   │   └── StreamingService.php      ← Stream LLM response to client
    │   │   │
    │   │   ├── Document/
    │   │   │   ├── DocumentParserService.php  ← PDF/Word → plain text
    │   │   │   ├── ChunkingService.php        ← Split text into chunks
    │   │   │   └── StorageService.php         ← Upload/download from MinIO
    │   │   │
    │   │   └── HR/
    │   │       ├── CandidateService.php       ← Business logic for candidates
    │   │       └── InterviewService.php       ← Generate interview questions
    │   │
    │   └── Message/                           ← Symfony Messenger async messages
    │       ├── ProcessDocumentMessage.php     ← Triggered after CV upload
    │       └── ProcessDocumentHandler.php     ← Parse → chunk → embed → store
    │
    ├── migrations/                            ← Doctrine DB migrations
    └── config/
        ├── packages/
        │   ├── messenger.yaml               ← Queue config (Redis transport)
        │   └── flysystem.yaml               ← MinIO/S3 config
        └── services.yaml
```

---

## 🗄️ Database Schema (Doctrine Entities)

### Key tables and what they do:

```sql
-- Multi-tenant: each company is a workspace
workspaces
  id, name, slug, created_at

-- Open job positions
job_positions
  id, workspace_id, title, description, requirements, status, created_at

-- Candidates applying to positions
candidates
  id, workspace_id, job_position_id, name, email, status, ai_score, created_at

-- All uploaded files (CVs, job descriptions, policies)
documents
  id, workspace_id, candidate_id, type, filename, s3_path, status, created_at

-- Text chunks extracted from documents (the RAG foundation)
document_chunks
  id, document_id, content (TEXT), embedding (VECTOR(1536)), page_number, chunk_index

-- Chat sessions between HR and a document/candidate
conversations
  id, workspace_id, user_id, context (JSON), created_at

-- Individual chat messages
messages
  id, conversation_id, role (user/assistant), content, sources (JSON), created_at
```

---

## 🔄 How the CV Processing Pipeline Works

```
1. HR uploads CV (PDF)
        ↓
2. File saved to MinIO (S3)
        ↓
3. ProcessDocumentMessage pushed to Redis queue
        ↓
4. Worker picks up the message (async)
        ↓
5. DocumentParserService extracts text from PDF
        ↓
6. ChunkingService splits text into ~500 token chunks
        ↓
7. EmbeddingService sends each chunk to OpenAI embeddings API
        ↓
8. Each chunk + its vector saved to document_chunks table (pgvector)
        ↓
9. Candidate status updated to "ready"
        ↓
10. HR can now: chat with CV, rank candidate, generate interview questions
```

---

## 🤖 AI Features — Module 1 Roadmap

| Feature | Endpoint | AI Pattern |
|---------|----------|------------|
| Upload & process CV | POST /api/candidates | Async queue + embeddings |
| Rank candidates vs job | POST /api/analysis/rank | RAG + structured output |
| Chat with a CV | POST /api/chat/message | Streaming RAG |
| Generate interview questions | POST /api/analysis/interview-questions | Prompt engineering |
| Compare candidates | POST /api/analysis/compare | Multi-doc RAG |

---

## 🧰 Useful Docker Commands

```bash
# Start everything
docker compose up -d

# Stop everything
docker compose down

# View app logs
docker logs hireiq_app -f

# View worker logs (async CV processing)
docker logs hireiq_worker -f

# Run Symfony console commands
docker exec hireiq_app php bin/console [command]

# Run migrations
docker exec hireiq_app php bin/console doctrine:migrations:migrate

# Create a new migration after changing an Entity
docker exec hireiq_app php bin/console doctrine:migrations:diff

# Open a shell inside the app container
docker exec -it hireiq_app bash

# Connect to PostgreSQL
docker exec -it hireiq_postgres psql -U hireiq -d hireiq
```

---

## ✅ Next Steps After Setup

1. **Create the Doctrine entities** (Document, DocumentChunk with vector column)
2. **Build the upload endpoint** — accept PDF, save to MinIO, push to queue
3. **Build the worker handler** — parse PDF, chunk text, call embeddings API, save vectors
4. **Build the RAG service** — vector search + LLM call
5. **Build the ranking endpoint** — score all candidates against a job description
6. **Build the streaming chat endpoint** — talk to any CV in real time

Ready to start with Step 1 (Entities + Migrations)? Just say the word! 🚀
