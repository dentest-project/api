# Dentest API

## Development

```bash
cd docker
docker-compose up --build
```

Access the API at http://localhost:8000

## Summary generation (OpenRouter)

Feature and path summaries are generated after write operations. Feature summaries refresh when a feature moves from `draft` to `ready_to_dev`, and path summaries refresh whenever the set of `live` features changes in a path subtree. This runs on `kernel.terminate` so the save response isn't blocked.

Steps:
- Set `OPENROUTER_API_KEY` in `.env.local`.
- Optionally override `SUMMARY_GENERATION_MODEL` if you want to pin a different model.
- No extra Docker service is required.
