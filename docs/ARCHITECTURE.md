# hofros Backend Architecture

## Core Structure

- `app/Http/Controllers/Api/V1`: Versioned API controllers.
- `app/Http/Requests/Api`: API request validation classes.
- `app/Services`: Business logic orchestration.
- `app/Repositories`: Data access layer abstractions.
- `app/Actions`: Single-purpose application actions.
- `app/DTOs`: Data transfer objects for explicit payload contracts.

## Routing Strategy

- Public web routes stay in `routes/web.php`.
- API routes are versioned from `routes/api.php` under `/api/v1`.

## Health Endpoint

- `GET /api/v1/health` returns app metadata and database connection status.
