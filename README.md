# Dentest API

## Development

```bash
cd docker
docker-compose up --build
```

Access the API at http://localhost:8000

## Feature summaries (OpenRouter)

Feature summaries are generated after saving features. This runs on `kernel.terminate` so the save response isn't blocked.

Steps:
- Set `OPENROUTER_API_KEY` in `.env.local`.
- The default model is `openrouter/free`, which lets OpenRouter choose a currently available free model.
- Optionally override `FEATURE_SUMMARY_MODEL` if you want to pin a specific `:free` model.
- No extra Docker service is required.
