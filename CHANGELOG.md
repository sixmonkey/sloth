# Changelog

All notable changes to `folivoro/sloth` are documented here.

## [2.0.0] — unreleased

### Breaking changes

- **Package renamed** from `sixmonkey/sloth` to `folivoro/sloth`
- **PHP 8.4** minimum requirement
- **`Configure` removed** — use `config()` and Laravel config files instead. See [upgrade guide](https://docs.folivoro.com/upgrade)
- **`LayotterBridge` extracted** — install `folivoro/layotter-bridge` separately if you use Layotter
- **`resources/views/`** replaces `src/_view/` as the framework view directory
- **`storage/`** always lives at the project root — update `.gitignore`
- All `Configure::write()` config keys have changed — see [key mapping](https://docs.folivoro.com/upgrade#key-mapping)

### Added

**Application:**
- `ApplicationPathTrait` — typed path accessors: `appPath()`, `themePath()`, `configPath()`, `storagePath()`, `cachePath()`, `logsPath()`, `cmsPath()`, `pluginsPath()`, `uploadsPath()`
- `ApplicationUriTrait` — `uri()`, `addUri()`
- `ApplicationEnvironmentTrait` — environment and mode detection
- `ApplicationProviderTrait` — provider lifecycle management
- `ApplicationConvenienceTrait` — `getAllModels()`, `getAllTaxonomies()` returning Collections
- `isThemeMode()` — explicit Theme vs Classic mode detection
- `.env` walk-up discovery — Sloth finds `.env` by walking up from the App-Root

**Config:**
- `config/app.php` — `relative_urls`, `relative_links`, `relative_uploads`, `wp_json.base_url`
- `config/theme.php` — `menus`, `image_sizes`, `supports`, `process_acf`
- `config/admin.php` — `hide_updates`, `footer`, `cleanup_menu`
- `mergeConfigFrom` and `publishes` on all framework providers
- `theme.supports` — register WordPress theme supports via config

**View:**
- `AbstractViewExtension` — engine-agnostic extension system with `getHelpers()`, `getDirectives()`, `share()`
- `TwigAdapter` — pluggable Twig engine adapter
- `SlothViewExtension` — replaces old `TwigExtension`/`Formatter`/`Helper` system
- View extensions auto-discovered from `Extensions/View/`
- `resources/views/` — framework views relocated from `src/_view/`
- `wrapper.twig` — modernised with semantic HTML and direct WordPress function calls

**Routing:**
- `UrlServiceProvider` — relative URL handling extracted from `Media`
- `RelativeUrlHandler` — `toRelativeUrl()`, `makeHrefsRelative()`, `makeSrcsRelative()`

**Console:**
- `make:module` — generates Module class + Twig template
- `make:model` — generates Model class
- `make:provider` — generates Service Provider (`--theme` / `--app` flags)
- `make:extension` — generates View Extension
- `make:layout` — generates Layout Twig template, interactive with full WordPress hierarchy + `Laravel\Prompts`
- `make:command` — generates WP-CLI Command
- `make:api-controller` — generates REST API Controller
- `stub:publish` — publishes stubs to `stubs/` for customisation
- All stubs in `resources/stubs/` — publishable and overridable

### Changed

- `Application` refactored into focused traits — smaller, more readable
- `guessBasePath()` now returns the project root (not App-Root) in Classic mode
- `MediaServiceProvider` — relative URL logic removed, now only handles image sizes and SVG mime type
- `path()` method deprecated — use typed accessors instead
- `AdminServiceProvider` — `hide_updates` and `footer` now config-driven
- `wrapper.twig` — `var themes_url` JS snippet removed

### Removed

- `Configure` class — use `config()` instead
- `SlothTwigExtension` / `Formatter` / `Helper` — use `AbstractViewExtension`
- `LayotterBridgeServiceProvider` — install `folivoro/layotter-bridge` separately
- `resources/views/Layotter/` — moved to `folivoro/layotter-bridge`
- `src/_view/` — replaced by `resources/views/`
- `theme.routes` config key
- `plugins.autoactivate` config key
- `autosync_acf` config key
- `core.hide_updates`, `plugins.hide_updates`, `themes.hide_updates` config keys (replaced by `admin.hide_updates.*`)

### Fixed

- `TwigAdapter` — `autoescape` now reads `view.autoescape` config key
- `AdminServiceProvider` — hide_updates filters now opt-in (default `false`) instead of opt-out

---

## [1.0.2] — 2024

- PHP 8.x compatibility fixes

## [1.0.1] — 2023

- Bug fixes

## [1.0.0] — 2023

- Initial stable release under `sixmonkey/sloth`
