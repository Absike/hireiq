# 🧠 HireIQ — AI-Powered HR Recruitment Platform

AI-powered recruitment platform that helps HR teams screen candidates faster using LLMs. Upload CVs, get AI-powered rankings, chat with candidate profiles using RAG, and automate HR workflows.

**Stack:** Symfony 7.4 · Vue.js 3 · TypeScript · PostgreSQL + pgvector · Groq LLM · Redis · Docker

---

## ⚡ Quick Start

### Prerequisites
- Docker + Docker Compose
- A free [Groq API key](https://console.groq.com)

### 1. Clone the repo
```bash
git clone git@github.com:Absike/hireiq.git
cd hireiq
```

### 2. Configure environment
```bash
cp .env.example .env
```

Edit `.env` and add your API key:
```
GROQ_API_KEY=your-groq-key-here
```

### 3. Start Docker
```bash
docker compose up -d
```

### 4. Run migrations
```bash
docker exec hireiq_app php /var/www/app/bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Clear cache
```bash
docker exec hireiq_app php /var/www/app/bin/console cache:clear
```

✅ App → http://localhost:8080  
✅ API → http://localhost:8080/api  
✅ MinIO → http://localhost:9001 (hireiq / hireiq123)

---

## 🐳 Docker Configuration (Current)

HireIQ runs from the root `docker-compose.yml` stack.

### Services

- `hireiq_app` (Symfony PHP app)
- `hireiq_nginx` (public HTTP entrypoint)
- `hireiq_postgres` (PostgreSQL 16 + pgvector)
- `hireiq_redis` (queue/cache backend)
- `hireiq_worker` (Symfony Messenger consumer)
- `hireiq_minio` (S3-compatible object storage)

### Ports

- App/API: `http://localhost:8080`
- Postgres: `localhost:5432`
- Redis: `localhost:6379`
- MinIO API: `localhost:9000`
- MinIO Console: `http://localhost:9001`

### Notes

- Use only the root compose stack (`docker compose ...` in repo root).
- If schema errors appear (e.g. missing tables), run migrations inside `hireiq_app`:

```bash
docker exec hireiq_app php /var/www/app/bin/console doctrine:migrations:migrate --no-interaction
```

---

## 🔄 CV Processing Pipeline

```
1. HR uploads CV (PDF)
        ↓
2. File saved to local storage
        ↓
3. ProcessDocumentMessage pushed to Redis queue
        ↓
4. Worker picks up the message (async)
        ↓
5. PDF parsed → plain text extracted
        ↓
6. Text split into ~500 char chunks
        ↓
7. Chunks stored in PostgreSQL + pgvector
        ↓
8. Groq LLM extracts: name, email, skills, experience, education
        ↓
9. Candidate status → "ready"
        ↓
10. HR can now: chat with CV, rank, generate interview questions
```

---

## 🤖 AI Features

| Feature | Endpoint | AI Pattern |
|---------|----------|------------|
| Upload & process CV | `POST /api/candidates` | Async queue + LLM extraction |
| Rank candidates vs job | `POST /api/analysis/rank` | RAG + structured output |
| Chat with a CV | `POST /api/conversations/{id}/messages` | Conversational RAG |
| Generate interview questions | `POST /api/analysis/interview-questions` | Prompt engineering |
| Compare candidates | `POST /api/analysis/compare` | Multi-doc analysis |

---

## 🧰 Useful Commands

```bash
./hireiq.sh worker:refresh    # Clear cache + restart worker + show logs
./hireiq.sh worker:logs       # Watch worker logs live
./hireiq.sh cache             # Clear Symfony cache
./hireiq.sh migrate           # Run database migrations
./hireiq.sh ps                # Show running containers
```

---

## 🌿 Branches

| Branch | Purpose |
|--------|---------|
| `master` | Stable production releases |
| `develop` | Active development |
