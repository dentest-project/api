# Dentest API

## Development

```bash
cd docker
docker-compose up --build
```

Access the API at http://localhost:8000

## Feature summaries (local model)

Feature summaries are generated locally after saving features. This runs on `kernel.terminate` so the save response isn't blocked.

Steps:
- Start the local model service (`ollama` is included in `docker-compose.override.yml`).
- Pull a model inside the container (example: `ollama pull llama3.2:3b`).
- Ensure your `.env.local` points to the model server (e.g. `FEATURE_SUMMARY_BASE_URL=http://ollama:11434`).
