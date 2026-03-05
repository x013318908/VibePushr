set shell := ["pwsh", "-NoLogo", "-NoProfile", "-Command"]

lint:
  ./scripts/lint.ps1

test:
  ./scripts/test.ps1

audit:
  ./scripts/audit.ps1

check: lint test audit

