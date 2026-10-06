# OpenVvEai security code review

**Review date:** 6 October 2026

**Reviewed version:** repository release source 8.50-beta, including the optional MFA changes

**Status:** static review completed. No critical or high-severity issue was confirmed in the reviewed source. Two medium-priority risks and one deployment boundary need attention before production use. This report is suitable for the project repository; it does not certify the system as secure.

## Summary in plain language

The app has several good baseline protections: each VvE is tied to a fixed installation ID and hostname; HTTPS and a private directory outside the webroot are required; passwords are stored as hashes; login attempts are limited; account sessions expire after inactivity; administrative changes use role checks and CSRF tokens; database values are generally passed as prepared parameters; and uploaded files are stored outside the public webroot under generated names.

The main remaining risks are around the backup automation URL and the sensitive contents of full application backups. Each VvE installation also shares the hosting account's operating-system boundary with the others. The project documentation already explains that a hosting-account owner can access every installation.

## Findings

### [P2] Backup automation uses a secret in the URL and performs work on GET

`backup_cron.php` accepts a bearer token in the query string and starts database and application backups on a GET request. URLs can be copied into hosting control panels and may be retained in access logs. Anyone who obtains the token can repeatedly trigger backup work until it is rotated. The token is 192 bits of randomness and is compared safely, so guessing it is not a practical concern; exposure and repeated work are the concerns.

**Suggested action:** move the token to a request header or another secret-bearing mechanism not recorded as the URL, require an explicit request method, and add execution throttling or a host-level access rule. Keep token rotation available.

### [P2] Full application backups contain live credentials and personal data

The GUI backup intentionally includes private configuration and user files. That can include database and mailbox credentials, an AI-provider API key, MFA key material, user hashes and recovery hashes, and uploaded VvE documents. The companion database backup contains the VvE records. Backups are stored with restrictive permissions and the UI warns users to protect copies, but the archives themselves are not encrypted.

**Suggested action:** encrypt exported archives with a key kept separately from the hosting account, or document and enforce an equally strong protected-storage process for every downloaded copy. Keep `mfa.key` together with the corresponding private user files in protected backups; recovery codes can be used if the key is lost.

### [P2] Tenant separation does not isolate hosting-account administrators

The application separates VvE data by installation, database, user file and private directory. It does not create separate operating-system users or hosting accounts. Anyone with the shared hosting account's file or database access can access every tenant. This is an explicit architecture limit, not a route-level authorization bypass found in this review.

**Suggested action:** use separate hosting accounts for VvE installations that must be protected from one another's hosting administrators or operators. Preserve this warning in public deployment documentation.

## Controls reviewed

- **Authentication:** password hashing, per-user accounts, rate limiting, inactivity timeout, session cookie flags, session ID regeneration, MFA challenge flow, single-use recovery codes and TOTP replay rejection.
- **Authorization and request protection:** authenticated application routes, admin checks for privileged write operations, CSRF checks on state-changing forms and APIs.
- **Tenant boundaries:** fixed tenant identity, hostname and HTTPS checks, private directory validation and database-to-installation ID binding.
- **Database access:** prepared statements for record values and validation/quoting around dynamic table and column identifiers.
- **Files and uploads:** generated storage names, file type and size checks, private storage paths, authenticated download routes and path resolution checks.
- **AI, email and backups:** features default off, credentials stored outside the public directory, source-sharing settings, cron-token flow and archive contents.
- **Web server rules:** directory listing disabled, HTTPS redirect, common sensitive extensions blocked and helper files denied directly.

## Checks run

- PHP syntax check passed for all top-level release PHP files, installer templates and PHP test files.
- Passed `test_mfa.php`, `test_assistant_financial.php`, `test_assistant_eml_sources.php`, `test_document_email_index.php` and `test_lookup_catalogs.php`.
- `git diff --check` passed for the working changes.
- The database isolation integration test was not run. Project instructions require a dedicated temporary MariaDB socket and two isolated databases; no such test database was provisioned for this review.

## Review limits and publication steps

This was a risk-based static code review. It did not include a live deployment test, penetration test, independent reviewer, dependency vulnerability scan, STRATO configuration audit, or legal review of ownership and licensing. The bundled PDF parser and other third-party components should be included in a dependency and license inventory before public release.

The workspace contains dated repair packages and other historical material alongside the current app. Publish from a curated source tree and stage only the intended release files; do not use a broad `git add .` from the workspace. The maker's rights declaration and chosen licenses are recorded in `docs/PUBLICATION_READINESS.md`; the name/logo search and independent visual comparison have documented limitations. Before changing GitHub visibility, review the exact public commit for credentials, personal data, backups and old patch archives.

## Overall assessment

The reviewed release has a reasonable baseline for a self-hosted beta, and no critical or high-severity code issue was confirmed in this static pass. Resolve or consciously accept the backup risks, retain the hosting-isolation warning, run the isolated integration test, and complete dependency and rights checks before describing it as production-ready or opening the repository publicly.
