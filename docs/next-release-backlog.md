# Next Release Backlog

This file tracks likely candidates for the next VibePushr release after v0.1.9.

## High Priority

- Restrict direct access to generated internal directories such as `.vp_data` on shared hosting environments where dot-prefixed directories may still be browsable.
- Decide the rollback design for mistaken uploads before implementation starts.

## Rollback Design Questions

- Keep rollback history for 30 days, matching the login-lock recovery window.
- Guarantee at least one usable rollback even if sync is triggered multiple times in a short period.
- Decide whether rollback should be history-based, grouped by folder sync sessions, or guarded by a warning before overwriting the previous backup.

## Operational Improvements

- Decide whether `CHANGELOG.md` should be maintained per release from now on.
- Consider adding a small publish checklist link directly inside the app UI for operators.
- Decide whether server-smoke verification results should be captured in a reusable issue template.

## Nice to Have

- Clarify whether login URL customization should remain an operator-only filename change or become an in-app feature later.
- Consider a safer default or guidance for hiding internal state directories on hosts without strong dot-directory protection.
