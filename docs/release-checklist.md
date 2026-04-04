# Release Checklist

Use this checklist before calling a VibePushr build a formal release.

## 1. Automated checks

From the repository root:

- `./scripts/lint.ps1`
- `./scripts/test.ps1`
- `./scripts/audit.ps1`

Optional all-in-one command:

- `./scripts/check.ps1`

## 2. Browser-level checks

From `dev/`:

- `npm run test:e2e:smoke`
- `npm run test:e2e:client-read-error`

Notes:

- Run `npm run pw:install` first on a fresh machine.
- Set `VP_E2E_PASSWORD` when testing against an already initialized instance.
- Set `VP_ENTRYPOINT` when verifying a renamed entrypoint such as `my-team-sync-gateway.php`.

## 3. Manual server verification

Verify at least once on a PHP hosting environment similar to the production target, such as XREA:

1. Upload `vp.php` and open it in a browser.
2. Complete first-time setup and confirm login succeeds.
3. Run a dry-run upload with a small folder.
4. Run a real sync with a small folder and confirm the published files are reachable.
5. Rename `vp.php`, reopen it, and confirm login, sync, and dry-run still work.
6. Trigger and recover a login guard lock in a safe test environment by deleting `public_html/.vp_data/.vp_login_guard.json`.

Detailed operator flow:

- `docs/server-smoke-checklist.md`

## 4. Release metadata

Before publishing:

1. Confirm the Git tag, GitHub Release title, and release notes all use the same version string.
2. Attach `vp.php` as the release asset.
3. Review `CHANGELOG.md`, `README.md`, `README.en.md`, and `docs/index.html` for version-agnostic guidance and broken links.
4. Confirm `SECURITY.md` is still accurate for the current reporting path.
5. Start from `docs/release-notes-v0.1.9.md` when drafting the GitHub Release body.
