# AI Usage Dashboard

Private PHP dashboard for OpenAI/Codex and Claude subscription usage.

## Current scope

- Password-protected dashboard.
- Manual provider values while subscription connectors are added.
- Bearer-token JSON ingest endpoint for a browser connector.
- Usage data stored outside the public UI and denied by `.htaccess`.

## Hostinger environment variables

Set these in the hosting environment or a server-side configuration file:

- `USAGE_APP_PASSWORD`: dashboard password.
- `USAGE_INGEST_TOKEN`: random token used by the private browser connector.

Do not commit either value.

## Ingest example

`POST /?action=ingest` with header `Authorization: Bearer <token>` and JSON such as:

```json
{"provider":"claude","used":"35%","remaining":"65%","reset":"in 2h 10m","source":"browser"}
```
