# VibePushr

Deploy folders, not files.

VibePushr is a lightweight folder deployment tool built for fast, frictionless publishing.

Upload a single file (`vp.php`) to your server, open it in a browser, and push an entire folder in seconds.

It is designed for **vibe coding workflows** where speed matters: build quickly with AI, then publish immediately without complex deployment pipelines.

FTP is slow.  
Git deploy is heavy.  
VibePushr sits in the middle.

---

## Features

- **Single-file deployment**  
  Install by uploading `vp.php` to your server.

- **Folder-based publishing**  
  Upload a folder and publish all contents to the server.

- **File-level transfer**  
  Files are transferred individually for better stability and retry behavior.

- **Optional gzip upload**  
  Text files can be compressed before upload to reduce transfer size.

- **Simple authentication**  
  Built-in login protection with password hashing.

- **Modern development workflow**  
  PHPUnit tests, linting, and CI are included for safer development.

---

## Target Environment

- Intended for servers running PHP 8.2 or later.
- Assumes you can upload a single PHP file through FTP or a hosting file manager.
- Best suited for lightweight hosting such as XREA-class shared environments.
- The only production artifact is `public_html/vp.php`. Everything under `dev/` is for development only.

---

## Installation

1. Download the latest `vp.php` from GitHub Releases.
2. Upload `public_html/vp.php` to your server.
3. Open it in your browser.
4. Complete the initial setup and set an admin password.

That’s it.

---

## Update

1. Download the latest `vp.php` from GitHub Releases.
2. Replace the existing `vp.php` on your server.
3. Open it in your browser.
4. Verify login, dry-run, and a small sync before calling the update complete.

---

## Recommended Security Operation

- On deployment, rename `vp.php` to a memorable but sufficiently long custom name (for example, `my-team-sync-gateway.php`).
- Renaming the file should not change behavior: login, sync, and dry-run continue to work.
- See [SECURITY.md](SECURITY.md) for reporting guidance and operator recommendations.

---

## Recommended Upload Workflow

Project folders often contain files that should not be uploaded to a server, such as development-only files, API keys, AI-agent notes, or unpublished drafts.
Instead of selecting the working folder directly, create a separate release folder that contains only the files you are comfortable publishing, then upload that folder with VibePushr.

1. Ask your AI agent to create a release folder with only the files that are safe to upload.
2. Review the generated release folder and confirm that it does not include secrets or unnecessary files.
3. In VibePushr, select that release folder, run a dry-run, and then upload it.

This repository does not require that workflow for its own development, but it is a safe and easy-to-understand way to use VibePushr.

---

## Directory Layout

- `public_html/vp.php` — deployable single-file application
- `docs/specs/` — specifications
- `dev/` — development dependencies and test environment
- `scripts/` — helper scripts (PowerShell)

Only `vp.php` is required for deployment.

---

## Quick Commands (PowerShell)

- `./scripts/lint.ps1` — syntax check
- `./scripts/test.ps1` — run PHPUnit
- `./scripts/audit.ps1` — dependency audit
- `./scripts/check.ps1` — run all checks
- `cd dev; npm run test:e2e:smoke` — run the release smoke checks
- `cd dev; npm run test:e2e:client-read-error` — verify retry and recovery behavior

---

## CI

GitHub Actions runs lint and PHPUnit on pushes and pull requests.

- `.github/workflows/ci.yml`
- Playwright E2E remains a manual pre-release verification step.

---

## Development Notes

- Main application code lives in `public_html/vp.php`.
- Tests and dependencies are isolated under `dev/`.
- The production artifact remains a single file.
- See [docs/release-checklist.md](docs/release-checklist.md) for the release checklist.
- See [CHANGELOG.md](CHANGELOG.md) for release notes.

---

## Authentication

During first launch, you set an admin password.

The password hash is stored in:

- `public_html/.vp_data/.vp_auth.php`

Login protection status is stored in:

- `public_html/.vp_data/.vp_login_guard.json`

If the login guard locks access, you can recover by removing the JSON file via FTP.

Other runtime state is also stored under `public_html/.vp_data/`.

---

## License

CC0 1.0 Universal  
Use it freely. Break it freely.
