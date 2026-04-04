# Changelog

All notable changes to VibePushr will be documented in this file.

## Unreleased

## v0.1.9

- Release docs were expanded for first-time setup, update, recovery, and manual release verification.
- The download page now points to the latest GitHub release asset instead of a hard-coded version URL.
- Added a security policy and a release checklist for formal release prep.
- Added dedicated Playwright commands for smoke and retry/error recovery checks.
- Improved Playwright startup and locale-sensitive assertions so release verification works more cleanly across Windows and Unix-like environments.

## v0.1.7

- Added dark mode support with improved contrast for tables, forms, buttons, and logs.
- Refined the folder picker around drag-and-drop plus click selection.
- Refreshed the folder list automatically after sync, dry-run, and retry actions.
- Collected runtime state under `public_html/.vp_data/`.
- Added login guard recovery guidance for locked access.
