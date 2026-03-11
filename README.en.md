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

## Installation

1. Upload `public_html/vp.php` to your server.
2. Open it in your browser.
3. Complete the initial setup and set an admin password.

That’s it.

---

## Recommended Security Operation

- On deployment, rename the `vp` portion of `vp.php` to a non-guessable custom name (for example, `a8k3push.php`).
- Renaming the file should not change behavior: login, sync, and dry-run continue to work.

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

---

## Optional: just

If `just` is installed:

- `just lint`
- `just test`
- `just audit`
- `just check`

---

## CI

GitHub Actions runs lint and PHPUnit on pushes and pull requests.

- `.github/workflows/ci.yml`

---

## Development Notes

- Main application code lives in `public_html/vp.php`.
- Tests and dependencies are isolated under `dev/`.
- The production artifact remains a single file.

---

## Authentication

During first launch, you set an admin password.

The password hash is stored in:

- `public_html/.vp_auth.php`

Login protection status is stored in:

- `public_html/.vp_login_guard.json`

If the login guard locks access, you can recover by removing the JSON file via FTP.

---

## License

CC0 1.0 Universal  
Use it freely. Break it freely.
