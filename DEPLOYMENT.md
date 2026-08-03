# Deployment Procedure

FieldWerkz runs on **Laravel Vapor** — a Docker-based serverless deployment on AWS (Lambda + CloudFront + RDS), managed via the `vapor` CLI. There is no traditional server to SSH into; every deploy builds a fresh container image and Vapor swaps traffic over to it.

- **Production URL:** https://fieldwerkz.com (https://farmtask.be also still resolves to the same environment during the migration off the old name)
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
   No `APP_URL=` prefix needed anymore — see "Critical: APP_URL during builds" below. This *was* required (and missed repeatedly: 2026-07-15, then again 2026-08-03) until it was fixed at the source in `vapor.yml` itself, so it can no longer depend on the person/session running the deploy remembering it.

   This single command:
   - Runs the `build` steps from `vapor.yml` (composer install, `npm run build`, route/view/event caching)
   - Builds a Docker image and pushes it to AWS ECR
   - Uploads compiled frontend assets to S3
   - Updates the Lambda function and switches traffic over
   - **Runs `php artisan migrate --force` automatically** (see `deploy:` in `vapor.yml`) — any pending migration goes live at this point, against the real production database
   - Takes roughly 10–15 minutes end to end. A `.vaporignore` file (gitignore-style syntax) excludes `vendor/`, `node_modules/`, `.git/`, and a few other directories from the build/Docker context — those get reinstalled fresh by `composer install`/`npm ci` regardless, so copying the local versions first was just wasted time (`vendor/` alone is 15,000+ files). If deploys start feeling slow again, check `.vaporignore` still exists and covers anything new and large.

3. **Verify it worked:**
   - `curl -s -o /dev/null -w "%{http_code}\n" https://fieldwerkz.com/login` should return `200`
   - Check migrations landed: `vapor command production --command="migrate:status"` — every migration should show `Ran`
   - **Always also check for the localhost-bundle bug** (see below) — a 200 status alone doesn't catch it, since the page loads fine, only client-side navigation silently breaks.

If a deploy fails, **check where it failed** before assuming anything went live:
- If it failed during the build/image-push stage (before "Updating Function Code"), production is untouched — the previous version is still serving traffic, and migrations have not run.
- Only once you see `Ensuring Storage Exists` / `Updating Function Code` onward has anything about the live environment started changing.

## Critical: APP_URL during builds

For a Docker-runtime project like this one, Vapor's `build:` commands (`composer install`, `php artisan ziggy:generate`, `npm run build`, etc.) run **locally on the machine invoking `vapor deploy`**, before the Docker image is assembled — they are not run inside the production environment. That means they read your **local** `.env` file, not the production one.

This matters specifically for `php artisan ziggy:generate`: it bakes the app's base URL into `resources/js/ziggy.js`, which then gets bundled into the shipped frontend JS and used as the base for every `route()`-generated link in the app (invitation-accept links, export downloads, any `route()` call in a React page). If your local `.env` has `APP_URL=http://localhost:8000` (the normal local dev setting), that's what ends up live in production — every generated link points at `localhost:8000` instead of the real domain, silently broken for anyone but whoever's running the local dev server. This is easy to miss: the page itself still loads fine (HTTP 200), only client-side `<Link>`/`router.visit()` navigation silently fails with a browser-console CORS error, so a quick "does the site load" check won't catch it.

This was originally "fixed" by remembering to prefix the deploy command with a real shell environment variable (`APP_URL=https://fieldwerkz.com vapor deploy production`) — but that depends on a human or an AI session remembering it every single time, and it was missed repeatedly (2026-07-15, then again 2026-08-03, causing a real production outage — "no links work"). A same-day attempt to fix it by embedding `APP_URL=https://fieldwerkz.com php artisan ziggy:generate` (shell prefix syntax) directly into `vapor.yml`'s build step *also* failed, for a different reason: Vapor's `build:` commands run through Windows `cmd.exe` on this machine, which doesn't understand POSIX `VAR=value command` syntax — the step errored (`'APP_URL' is not recognized as an internal or external command`), and the deploy silently continued using stale build artifacts, shipping the same broken bundle a second time.

**As of 2026-08-03, this is fixed properly**: `ziggy:generate` has a native `--url` flag, so `vapor.yml`'s production build step is `php artisan ziggy:generate --url=https://fieldwerkz.com` — a plain CLI argument, no shell env-var syntax at all, so it works identically on Windows and Unix. Don't reintroduce the `APP_URL=... command` shell-prefix pattern in `vapor.yml` — always use `--url` for this specific command. (Also note: `.env.production` locally still has the stale `APP_URL=https://farmtask.be` from before the domain migration — don't use `--env=production` as an alternative fix without correcting that first.)

**How to tell if this has happened:** fetch the live app's JS bundle and search for `localhost`:
```
curl -s https://fieldwerkz.com/login | grep -o 'src="[^"]*app-[^"]*\.js"'
curl -s "<that asset URL>" | grep -o "localhost[^\"']*"
```
If that prints anything, the bundle has the wrong base URL baked in — redeploy with the `APP_URL=` prefix above to fix it.

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
