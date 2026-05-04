## ArbeitszeitCheck 1.2.8

### Highlights

- Hardens all state-changing endpoints with Nextcloud CSRF protection while preserving read-only GET behavior.
- Sanitizes public JSON and page-render error responses so technical exception details are not leaked to users.
- Improves accessibility across primary app pages with consistent main landmarks, heading hierarchy, table names, live regions, focus indicators, and mobile touch targets.
- Fixes organization-wide monthly report downloads when a preview returns no matching members with entries.

### Included hardening work

- Corrects unauthenticated settings updates to return HTTP 401 instead of HTTP 400.
- Replaces raw dashboard widget exceptions with localized live-region error copy.
- Unifies clock-in copy around "Resume after break".
- Removes stale personal-settings placeholders and points users to the persisted in-app settings flow.
- Confirms the hardened accessibility test suite: 455 tests and 1,652 assertions green.
