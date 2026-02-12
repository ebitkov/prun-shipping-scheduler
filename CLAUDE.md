# PrUn Shipping Scheduler

Shipping schedule dashboard for [Prosperous Universe](https://prosperousuniverse.com/), built with Symfony 8 + PHP 8.5.

## Project Structure

- **Entity** (`src/Entity/`): Doctrine ORM entity. `User` — stores `fioUsername` (unique, user identifier), `password` (bcrypt-hashed FIO API token for Symfony auth), `fioApiKey` (plaintext token for FIO API calls). Table: `app_user`.
- **DTOs** (`src/Dto/`): Immutable `readonly` classes and enums. `ShippingTask`, `ShippingTaskType` (Import/Export), `ShipClass` (LCB/WCB/VCB), `Material`, `ProductionLine`, `ProductionOrder`, `RecipeIO`, `Storage`, `StorageItem`.
- **Services** (`src/Service/`):
  - `FioApiClient` — HTTP client for FNAR REST API (`rest.fnar.net`). Uses two Symfony cache pools: `fio.static_data` (24h) for materials, `fio.dynamic_data` (5min) for player data. Auth via `Authorization` header. Cache keys are prefixed with the FIO username to isolate data between users (e.g. `fio_{username}_player_planets`). Global data like `fio_all_materials` is shared.
  - `FioApiClientInterface` — contract for the API client.
  - `FioApiClientFactory` — creates per-user `FioApiClient` instances. `createForCurrentUser()` reads credentials from the Symfony security context. `createWithCredentials()` accepts explicit API key + username (for CLI). Wired as factory for `FioApiClientInterface` in `services.yaml`.
  - `MaterialRegistry` — lazy-loaded material lookup by ticker.
  - `ProductionAnalyzer` — calculates daily consumption/production rates from production lines.
  - `ShippingCalculator` — core business logic: computes import/export tasks with ship class selection, shipload amounts, and due dates.
  - `PriceService` — fetches CX prices from `/csv/prices` CSV endpoint, parses `AI1-AskPrice`/`AI1-BidPrice` columns. Cached via `fio.dynamic_data`.
- **Controllers** (`src/Controller/`):
  - `DashboardController` — `GET /`, renders the shell template (no data fetching). Requires `ROLE_USER`.
  - `ApiController` — `GET /api/dashboard/stream`, SSE endpoint that streams planet progress, tasks, prices, and completion events. Uses `StreamedResponse`.
  - `SecurityController` — `GET|POST /login`, `GET /logout`. Login form with Symfony form_login authenticator. Logout intercepted by firewall.
  - `RegistrationController` — `GET|POST /register`. Validates input, checks uniqueness, hashes token, persists user.
- **Frontend**: Symfony AssetMapper (no Webpack/Encore). `assets/app.js` + `assets/styles/app.css`. Dark theme with PrUn-inspired styling. Client-side rendering via SSE — JS handles date grouping (German labels), number formatting (`Intl.NumberFormat('de-DE')`), XIT ACT JSON generation, and price totals.
- **Templates**: Twig. `templates/base.html.twig` (layout), `templates/dashboard/index.html.twig` (shell with loading UI, user header, XIT ACT modal), `templates/security/login.html.twig`, `templates/security/register.html.twig`.

## Authentication & Multi-User

- **Database**: SQLite (`var/data.db`), managed via Doctrine ORM + Doctrine Migrations.
- **User model**: FIO API token serves dual purpose — hashed as login password, stored plaintext for API calls.
- **Security config** (`config/packages/security.yaml`): Entity provider on `User.fioUsername`, `form_login` with custom param names `_fio_username` / `_fio_api_token`. Access control: `/login` and `/register` are public, everything else requires `ROLE_USER`.
- **Per-request DI**: `FioApiClientInterface` is wired via factory (`FioApiClientFactory::createForCurrentUser`). In PHP-FPM, this is called once per request — all services that depend on the interface get the authenticated user's client.
- **Cache isolation**: User-specific cache keys are prefixed with `fio_{username}_`. Global data (`fio_all_materials`) is shared across users.
- **CLI fallback**: `FioInventoryCommand` injects `FioApiClientFactory` directly and accepts `--api-key` / `--username` options (with `FIO_API_KEY`/`FIO_USERNAME` env var fallback).

## Key Conventions

- All DTOs are `readonly` with typed constructor promotion.
- Constructor injection everywhere, autowired via `config/services.yaml`.
- `src/Entity/` is excluded from service auto-registration.
- Env vars: `FIO_BASE_URL` (shared infrastructure), `FIO_API_KEY`/`FIO_USERNAME` (optional, CLI fallback only).
- German UI labels (Heute, Morgen, Anmelden, Registrieren, Abmelden).
- Number formatting: German style — PHP `number_format(0, ',', '.')`, JS `Intl.NumberFormat('de-DE')`.

## Quality Tools

- **PHPStan**: `vendor/bin/phpstan analyse` (config in `phpstan.neon`, level max). Strict — catches nullable array keys from `str_getcsv`, etc.
- **PHPUnit**: `vendor/bin/phpunit` (config in `phpunit.dist.xml`). 17 tests, 46 assertions.
- Run both after every change.

## Architecture: SSE Dashboard Loading

The dashboard uses a lazy-load pattern:
1. `DashboardController` renders an empty shell with a loading UI (planet progress list).
2. `app.js` opens an `EventSource` to `/api/dashboard/stream`.
3. `ApiController` streams SSE events: `planets` (list of IDs), `planet-active`/`planet-done` (per-planet progress), `tasks` (per-planet task batches), `progress`/`prices`, `done`.
4. JS accumulates tasks, then renders everything client-side on `done` — grouping by German date labels, computing prices, building XIT ACT JSON.
5. `ShippingCalculator::calculateForPlanet()` is public so the API controller can call it per-planet.

## Technical Notes

- `str_getcsv()` returns `list<string|null>` — always null-check values before using as array keys (PHPStan will catch this).
- Twig `json_encode` filter uses PHP's `json_encode` — empty hashes `{}` in Twig encode as `[]` in JSON (empty PHP array). Build JSON as raw strings when exact `{}` output is needed (see XIT ACT script generation in `app.js`).
- Twig autoescape handles `"` → `&quot;` in data attributes; browsers decode entities in attribute values, so `JSON.parse(element.dataset.*)` works correctly.
- Cache pools defined in `config/packages/cache.yaml`.
- `AbstractController` has a `stream()` method — avoid naming controller actions `stream()` as PHPStan will flag Liskov substitution violations.
- SSE responses need `Content-Type: text/event-stream`, `Cache-Control: no-cache`, and `X-Accel-Buffering: no` (for nginx). Use `ob_flush()` + `flush()` after each event.
- Doctrine auto-generated `$id` properties need `// @phpstan-ignore property.unusedType` at PHPStan level max since Doctrine sets them via reflection.
- SQLite doesn't support `doctrine:database:create` — the DB file is created automatically on first migration. Use `doctrine:migrations:migrate` directly.
