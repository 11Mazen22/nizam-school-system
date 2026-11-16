# Security policy

## Supported release

Only the current `master` release and its latest packaged installer are
supported. Apply security updates to both online and offline installations;
the two targets share application code but have separate runtime packages.

## Reporting a vulnerability

Do **not** open a public issue containing an exploit, credentials, student or
staff data, database exports, logs, or screenshots from a real school.

Use GitHub's **Report a vulnerability** / private security-advisory flow for
this repository. Include:

- affected version or commit;
- deployment type (Railway/PostgreSQL or offline/MySQL);
- minimal reproduction using synthetic data;
- impact and whether authentication is required;
- suggested remediation, if known.

Do not access, alter, or download records that are not yours while testing.
Stop after proving the issue with the minimum synthetic evidence.

## Credential incidents

Removing a secret from the current file is not remediation: Git history,
build logs, screenshots, caches, and already-built images may retain it.

1. Revoke/rotate the credential at its provider immediately.
2. Replace it in Railway/GitHub or the offline installation.
3. Redeploy/restart affected services.
4. Review provider, Railway, application, and audit logs.
5. Invalidate sessions or recovery tokens when relevant.
6. Preserve evidence without copying student data into an issue.

Production credentials belong only in provider secret stores or the generated,
gitignored per-installation config—not source, documentation, or test fixtures.
