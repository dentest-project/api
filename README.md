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
- Set `SUMMARY_GENERATION_MODEL`. `openrouter/free` is fine for development, but production should set an explicit model so summary quality stays predictable.
- Optionally set `SUMMARY_GENERATION_FALLBACK_MODELS` to a comma-separated list of fallback models.
- Tune `SUMMARY_GENERATION_MAX_RETRIES`, `SUMMARY_GENERATION_RETRY_DELAY_MS`, and `SUMMARY_GENERATION_MAX_RETRY_DELAY_MS` if you want the API to retry temporary `429` and `5xx` responses.
- No extra Docker service is required.
