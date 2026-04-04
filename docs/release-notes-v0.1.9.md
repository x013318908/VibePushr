# VibePushr v0.1.9

VibePushr is a single-file PHP tool for fast folder publishing.

This release focuses on safer operations, cleaner release guidance, and better pre-release verification.

## Highlights

- Improved release documentation for first-time setup, updates, and recovery.
- Added a formal release checklist for automated checks, browser checks, and manual server verification.
- Added a security policy with reporting guidance and operator recommendations.
- Improved Playwright setup so browser checks work cleanly on Windows as well as Unix-like environments.
- Updated browser tests to handle both Japanese and English UI labels during release verification.

## What to download

- `vp.php`

Upload `vp.php` to your server, open it in a browser, and complete initial setup.

## Recommended release notes summary

VibePushr v0.1.9 improves release readiness around documentation, security guidance, and browser-level verification. It also makes the Playwright-based pre-release checks work more reliably across platforms.

## Verification used for this release candidate

- `php -l public_html/vp.php`
- `composer test`
- `composer audit`
- `npm run test:e2e:smoke`
- `npm run test:e2e:client-read-error`

## Manual follow-up before publishing

- Attach `vp.php` to the GitHub Release.
- Confirm the Git tag and release title both use `v0.1.9`.
- Run the server-side checks in `docs/release-checklist.md`.
