# Deployment Procedure

FarmTask runs on **Laravel Vapor** — a Docker-based serverless deployment on AWS (Lambda + CloudFront + RDS), managed via the `vapor` CLI. There is no traditional server to SSH into; every deploy builds a fresh container image and Vapor swaps traffic over to it.

- **Production URL:** https://farmtask.be
- **Config file:** `vapor.yml` (defines the `production` and `staging` environments)
- **Region:** ap-southeast-2 (Sydney)

## One-time setup (per machine)

You only need to do this once on any machine you plan to deploy from:

1. **Docker Desktop** must be installed and running — Vapor builds a real container image locally before uploading it.
2. **Disable Docker Desktop's containerd image store.** Go to Docker Desktop → Settings → General → uncheck **"Use containerd for pulling and storing images"** → Apply & Restart.
   - Why: with it enabled, `docker build` produces OCI-format image manifests, and AWS Lambda's container support only accepts classic Docker-format manifests. Deploys fail at the very last step ("Updating Function Code") with an error like:
     > AWS: The image manifest, config or layer media type for the source image ... is not supported.
   - Check current status any time with `docker info` — look for `Storage Driver: overlay2` (good) vs a `driver-type: io.containerd.snapshotter.v1` line (bad, needs disabling).
3. **Install the Vapor CLI**: `composer global require laravel/vapor-cli`
4. **Log in**: `vapor login`
5. Confirm you're in the right team: `vapor team:current`

## Standard deploy procedure

1. **Commit and push your changes** to GitHub (`origin/main`). Vapor deploys from your local project directory, not directly from GitHub, but keeping `main` in sync is how the team tracks what's actually live.
   ```
   git add -A
   git commit -m "..."
   git push origin main
   ```
2. **Deploy:**
   ```
   vapor deploy production
   ```
   This single command:
   - Runs the `build` steps from `vapor.yml` (composer install, `npm run build`, route/view/event caching)
   - Builds a Docker image and pushes it to AWS ECR
   - Uploads compiled frontend assets to S3
   - Updates the Lambda function and switches traffic over
   - **Runs `php artisan migrate --force` automatically** (see `deploy:` in `vapor.yml`) — any pending migration goes live at this point, against the real production database
   - Takes roughly 10–15 minutes end to end

3. **Verify it worked:**
   - `curl -s -o /dev/null -w "%{http_code}\n" https://farmtask.be/login` should return `200`
   - Check migrations landed: `vapor command production --command="migrate:status"` — every migration should show `Ran`

If a deploy fails, **check where it failed** before assuming anything went live:
- If it failed during the build/image-push stage (before "Updating Function Code"), production is untouched — the previous version is still serving traffic, and migrations have not run.
- Only once you see `Ensuring Storage Exists` / `Updating Function Code` onward has anything about the live environment started changing.

## Environment variables

Production environment variables are managed through Vapor, not committed to git (`.env` and `.env.production` are gitignored).

- **View/edit locally:** `vapor env:pull production` downloads the current file to `.env.production`. Edit it, then:
- **Push changes back:** `vapor env:push production --no-interaction`
  - Use `--no-interaction` — without it, the command can prompt for confirmation and hang silently if run non-interactively (e.g. in a background process).
- **Changing env vars alone does not redeploy the app.** You must run `vapor deploy production` afterward for the new values (or any new Composer/npm packages) to actually take effect.

## Rollback

If a deploy goes out broken:
```
vapor rollback production
```
This reverts to the previous deployment. Note it does **not** undo any database migrations that already ran — if the bad deploy included a destructive migration, that needs a manual fix regardless of rollback.

## Maintenance mode

To take the site down deliberately (e.g. for a risky manual DB change):
```
vapor down production
vapor up production   # bring it back
```

## Staging environment

`vapor.yml` also defines a `staging` environment, pointed at a separate (Railway-hosted) MySQL database rather than AWS RDS. Deploy to it the same way: `vapor deploy staging`. Useful for testing a risky change before it touches the real production database.

## Redeploying without new commits

If you just need to re-run the exact same build (e.g. after an infra-only fix like the Docker Desktop setting above, with no code changes since the last deploy attempt), `vapor redeploy production` re-runs the most recent deployment rather than building fresh — faster, but only valid when nothing in the code needs rebuilding.
