# PrUn Shipping Scheduler

Shipping schedule dashboard for [Prosperous Universe](https://prosperousuniverse.com/), built with Symfony 8 + PHP 8.5.

## Project Structure

- **DTOs** (`src/Dto/`): Immutable `readonly` classes and enums. `ShippingTask`, `ShippingTaskType` (Import/Export), `ShipClass` (LCB/WCB/VCB), `Material`, `ProductionLine`, `ProductionOrder`, `RecipeIO`, `Storage`, `StorageItem`.
- **Services** (`src/Service/`):
  - `FioApiClient` — HTTP client for FNAR REST API (`rest.fnar.net`). Uses two Symfony cache pools: `fio.static_data` (24h) for materials, `fio.dynamic_data` (5min) for player data. Auth via `Authorization` header.
  - `FioApiClientInterface` — contract for the API client.
  - `MaterialRegistry` — lazy-loaded material lookup by ticker.
  - `ProductionAnalyzer` — calculates daily consumption/production rates from production lines.
  - `ShippingCalculator` — core business logic: computes import/export tasks with ship class selection, shipload amounts, and due dates.
  - `PriceService` — fetches CX prices from `/csv/prices` CSV endpoint, parses `AI1-AskPrice`/`AI1-BidPrice` columns. Cached via `fio.dynamic_data`.
- **Controllers** (`src/Controller/`):
  - `DashboardController` — `GET /`, renders the shell template (no data fetching).
  - `ApiController` — `GET /api/dashboard/stream`, SSE endpoint that streams planet progress, tasks, prices, and completion events. Uses `StreamedResponse`.
- **Frontend**: Symfony AssetMapper (no Webpack/Encore). `assets/app.js` + `assets/styles/app.css`. Dark theme with PrUn-inspired styling. Client-side rendering via SSE — JS handles date grouping (German labels), number formatting (`Intl.NumberFormat('de-DE')`), XIT ACT JSON generation, and price totals.
- **Templates**: Twig. `templates/base.html.twig` (layout), `templates/dashboard/index.html.twig` (shell with loading UI and XIT ACT modal).

## Key Conventions

- No database — purely API-driven with Symfony Cache for persistence.
- All DTOs are `readonly` with typed constructor promotion.
- Constructor injection everywhere, autowired via `config/services.yaml`.
- Env vars: `FIO_BASE_URL`, `FIO_API_KEY`, `FIO_USERNAME` (bound as string params in services.yaml).
- German UI labels (Heute, Morgen, weekday names).
- Number formatting: German style — PHP `number_format(0, ',', '.')`, JS `Intl.NumberFormat('de-DE')`.

## Quality Tools

- **PHPStan**: `vendor/bin/phpstan analyse` (config in `phpstan.neon`). Strict — catches nullable array keys from `str_getcsv`, etc.
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
