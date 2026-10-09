# Commands

```bash
php artisan ai-meter:prune            # delete call records older than prune.keep_days
php artisan ai-meter:update-prices    # refresh the price book so token costs don't drift
php artisan ai-meter:export           # export the (filtered) call log as CSV or JSON
```

## ai-meter:prune

Deletes call records older than the retention window (`prune.keep_days`, default 90). Pass
`--days=` to override.

## ai-meter:update-prices

Refreshes the price book from a source (a URL or local JSON file, defaulting to the package's
maintained book) into an app-owned file, so token costs stay current without a package upgrade.

```bash
php artisan ai-meter:update-prices --dry-run          # preview: N new, M changed
php artisan ai-meter:update-prices --path=storage/app/ai-meter/prices.json
```

Point `pricing.prices_path` at that file; it loads **above** the bundled table but **below**
your explicit `pricing.prices` overrides, so a refresh never clobbers custom prices. Use
`--replace` to overwrite instead of merge.

## ai-meter:export

Exports the call log as CSV or JSON, to a file or stdout, with the same filters as the
dashboard plus a date window.

```bash
php artisan ai-meter:export --format=json --output=storage/app/calls.json
php artisan ai-meter:export --provider=openai --since=2024-06-01 --until=2024-06-30 --output=june.csv
php artisan ai-meter:export --with-io > calls.csv   # include prompt/response + properties
```

Metadata columns are always included; prompt/response text and `properties` are added only
with `--with-io`.
