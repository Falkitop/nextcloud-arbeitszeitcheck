# ArbeitszeitCheck — Mobile app API contract

This document describes the HTTP API used by the proprietary **ArbeitszeitCheck Employee** mobile app (iOS/Android). The app authenticates with **Nextcloud Login Flow v2** and uses **HTTP Basic authentication** with a dedicated app password. It sends `OCS-APIRequest: true` on every request.

## Authentication

1. `POST {server}/index.php/login/v2` — start Login Flow v2
2. User completes login in the system browser (`openAuthSessionAsync`)
3. App polls the poll endpoint until credentials are returned
4. All app API calls: `Authorization: Basic base64(loginName:appPassword)`

The user's main password is never stored on the device.

## Cold start

`GET /index.php/apps/arbeitszeitcheck/api/mobile/bootstrap`

Returns employee widget data, permissions (`canManage`, `isAdmin`), locale, feature flags, and `pushAvailable` (whether the Nextcloud **notifications** app is enabled for the user).

## Time tracking

| Method | Path | Notes |
|--------|------|--------|
| GET | `/api/dashboard-widget/employee` | Dashboard summary |
| GET | `/api/clock/status` | Current status |
| POST | `/api/clock/in` | Clock in |
| POST | `/api/clock/out` | Clock out |
| POST | `/api/break/start` | Start break |
| POST | `/api/break/end` | End break |

Errors: `400` business rules, `409` month finalized, `423` concurrent lock (retry).

## Time entries

| Method | Path |
|--------|------|
| GET | `/api/time-entries?start_date=&end_date=` |
| GET | `/api/time-entries/{id}` |
| POST | `/api/time-entries/{id}/request-correction` | JSON body: `{ "justification": "..." }` (min. 10 chars) |

## Absences

| Method | Path |
|--------|------|
| GET | `/api/absences` |
| POST | `/api/absences` | `{ type, start_date, end_date, reason? }` (dates `Y-m-d`) |
| GET | `/api/absences/{id}` |
| POST | `/api/absences/{id}/cancel` |

## Manager (requires manager permission)

| Method | Path |
|--------|------|
| GET | `/api/manager/pending-approvals` |
| GET | `/api/manager/team-overview` |
| POST | `/api/manager/absences/{id}/approve` | optional `{ comment }` |
| POST | `/api/manager/absences/{id}/reject` | optional `{ comment }` |
| POST | `/api/manager/time-entries/{id}/approve-correction` |
| POST | `/api/manager/time-entries/{id}/reject-correction` | optional `{ reason }` |

## Push notifications

Requires the Nextcloud **notifications** app on the server. The mobile app registers via the standard Nextcloud push API (`/ocs/v2.php/apps/notifications/api/v2/push` or v3). ArbeitszeitCheck only **creates** notifications through `OCP\Notification\IManager`; it does not store device tokens.

## GDPR

| Method | Path |
|--------|------|
| GET | `/gdpr/export` | Data export |
| POST | `/gdpr/delete` | Deletion request |

## Security notes

- HTTPS only from the mobile app
- Mobile-facing POST routes are annotated with `#[NoCSRFRequired]` (Basic auth, no session cookie)
- Clock endpoints use `#[BruteForceProtection]` and `#[UserRateLimit]`
- Server is authoritative for ArbZG rules; the app never submits client-side clock times for clock-in/out
