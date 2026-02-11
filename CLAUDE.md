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
- **Controller** (`src/Controller/DashboardController.php`): Single route `GET /`, groups tasks by German date labels (Heute, Morgen, weekday names, d.m. format).
- **Frontend**: Symfony AssetMapper (no Webpack/Encore). `assets/app.js` + `assets/styles/app.css`. Dark theme with PrUn-inspired styling.
- **Templates**: Twig. `templates/base.html.twig` (layout), `templates/dashboard/index.html.twig` (main view with XIT ACT modal).

## Key Conventions

- No database — purely API-driven with Symfony Cache for persistence.
- All DTOs are `readonly` with typed constructor promotion.
- Constructor injection everywhere, autowired via `config/services.yaml`.
- Env vars: `FIO_BASE_URL`, `FIO_API_KEY`, `FIO_USERNAME` (bound as string params in services.yaml).
- German UI labels (Heute, Morgen, weekday names).
- Number formatting: German style `number_format(0, ',', '.')`.

## Quality Tools

- **PHPStan**: `vendor/bin/phpstan analyse` (config in `phpstan.neon`). Strict — catches nullable array keys from `str_getcsv`, etc.
- **PHPUnit**: `vendor/bin/phpunit` (config in `phpunit.dist.xml`). 15 tests, 39 assertions.
- Run both after every change.

## Technical Notes

- `str_getcsv()` returns `list<string|null>` — always null-check values before using as array keys (PHPStan will catch this).
- Twig `json_encode` filter uses PHP's `json_encode` — empty hashes `{}` in Twig encode as `[]` in JSON (empty PHP array). Build JSON as raw strings when exact `{}` output is needed (see XIT ACT script generation).
- Twig autoescape handles `"` → `&quot;` in data attributes; browsers decode entities in attribute values, so `JSON.parse(element.dataset.*)` works correctly.
- Cache pools defined in `config/packages/cache.yaml`.
