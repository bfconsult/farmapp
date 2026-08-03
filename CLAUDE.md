# Fieldwerkz (farmapp)

Job/property-management app — jobs, work sessions, teams, property zones ("paddocks"), recurring jobs, billing rates, and shareable activity diaries for approvers. Solo project by James Billson, started 2026-06-30, in active daily development. Originally called **FarmTask**; renamed to **Fieldwerkz** around 2026-07-15 (production domain fieldwerkz.com). Watch for stray "FarmTask" branding in older code.

## Stack

- Laravel 12 (PHP 8.2) + Inertia.js v2 + React 19, Tailwind v4
- Pest for tests
- Deployed on Laravel Vapor (serverless, AWS Lambda/CloudFront/RDS, ap-southeast-2)
- `composer run dev` starts server + queue + pail logs + vite together

## Related repo

Companion mobile app is a **separate** sibling repo: `C:\xampp8\www\farmtask-tracker` (Flutter, Android-first, OS-level geofencing, feeds WorkSessions via the API). Own git repo, own working directory, own Claude memory store — check there for current mobile-app status rather than assuming this repo's notes are current.

## Picking up work

This project moves fast (multiple commits/day). Always check `git log` for the latest state before assuming any prior summary — including this file — is current.

**For full project history and feature-by-feature notes**, this project's Claude memory index is the source of truth:
`C:\Users\james\.claude\projects\C--xampp8-www-farmapp\memory\MEMORY.md`

That memory loads automatically when a Claude Code session starts **from this directory**. If a session instead started elsewhere (e.g. a generic Downloads/home folder) and has no context, point it at the path above.

## Standing rules

- Never run `migrate:fresh` or otherwise wipe the local DB without asking first — local dev data isn't disposable.
- For rendering bugs, drive the real app with Playwright before proposing fixes; don't expand scope without asking first.
- New routes need `php artisan ziggy:generate` before the frontend `route()` helper can see them.
