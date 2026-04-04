# Security Policy

## Supported Artifact

The only production artifact of VibePushr is `public_html/vp.php`.

The development environment under `dev/` exists for testing and maintenance. Report issues that affect either the production artifact or the bundled release guidance.

## Reporting a Vulnerability

Please do not open a public GitHub issue for a suspected security vulnerability.

Instead, use one of these private channels:

- GitHub Security Advisories for this repository, if enabled
- A private message to the repository owner before public disclosure

Include, when possible:

- affected VibePushr version or commit
- server environment details, including PHP version
- reproduction steps
- expected impact
- any suggested mitigation

## Response Expectations

The maintainer should aim to:

- acknowledge the report within 7 days
- confirm severity and reproduction status
- publish a fix or mitigation note as soon as practical

## Operational Guidance

For operators running VibePushr:

- rename `vp.php` to a memorable but sufficiently long custom filename before exposing it
- set a strong admin password during first launch
- keep `public_html/.vp_data/` writable by PHP but not publicly browsable
- if access is locked by the login guard, remove `public_html/.vp_data/.vp_login_guard.json` through FTP or hosting file tools after confirming the environment is safe
