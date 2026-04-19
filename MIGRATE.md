# Sloth Migration Guide

This document describes breaking changes between Sloth versions and how to fix them.
Many changes can be applied automatically — see [Automated Migration](#automated-migration).

---

## Automated Migration

Migration rules live in a separate companion package — `sixmonkey/sloth-rector` —
to keep Sloth's production dependencies clean.

### Install

```bash
composer require --dev sixmonkey/sloth-rector
```

### Run once after upgrading Sloth

```bash
composer sloth:migrate
```

Or manually:

```bash
vendor/bin/rector process app/ --config vendor/sixmonkey/sloth-rector/config/sloth-migrate.php
```

### Add to your theme's composer.json

```json
"scripts": {
    "sloth:migrate": "rector process app/ --config vendor/sixmonkey/sloth-rector/config/sloth-migrate.php"
}
```

Always review the changes before committing. Rector handles the mechanical fixes —
edge cases may still require manual attention.

---

## Breaking Changes

### Step 5 — `refactor/providers-cleanup`

---

#### `$layotter` — typed property removed

**Why:** PHP 8.4 disallows redeclaring a typed static property in a child class
with a different or missing type. `public static bool $layotter` in the base
`Model` caused fatal errors in any theme model that redeclared it.

**Before:**
```php
class Page extends Model
{
    public static bool $layotter = true;
    // or
    public static array $layotter = ['allowed_row_layouts' => [...]];
}
```

**After — remove the type declaration:**
```php
class Page extends Model
{
    public static $layotter = true;
    // or
    public static $layotter = ['allowed_row_layouts' => [...]];
}
```

**Rector:** ✅ Automated via `composer sloth:migrate`

---

#### `$options`, `$names`, `$labels`, `$icon`, `$register` — typed properties removed

**Why:** Same PHP 8.4 inheritance issue. These properties are now managed internally
via `HasLegacyArgs` and read transparently through `__get()`.

**Before:**
```php
class NewsModel extends Model
{
    protected array $options = ['public' => true];
    protected array $names   = ['singular' => 'News', 'plural' => 'News'];
    protected array $labels  = [];
    public bool $register    = true;
}
```

**After — remove type declarations:**
```php
class NewsModel extends Model
{
    protected $options = ['public' => true];
    protected $names   = ['singular' => 'News', 'plural' => 'News'];
    protected $labels  = [];
    public $register   = true;
}
```

**Rector:** ✅ Automated via `composer sloth:migrate`

---

#### `getRegistrationArgs()` — moved to `ModelRegistrar`

**Why:** Registration logic belongs in the Registrar, not in the data Model.

**Before:** Some themes overrode `getRegistrationArgs()` on their models.

**After:** Override is no longer possible via Model. If you need custom registration
args, set them via `$options` on your model — the Registrar merges them automatically.

**Rector:** ⚠️ Manual — Rector will warn if `getRegistrationArgs()` is found in a
theme model, but the fix requires moving the logic to `$options`.

---

#### `getLabels()` — moved to `ModelRegistrar`

**Why:** Label generation is part of registration, not Model data.

**Before:** Some themes overrode `getLabels()` on their models.

**After:** Use `$labels` or `$names` on your model instead — the Registrar reads
them via `__get()` and generates labels automatically.

```php
// Before: overriding getLabels()
public function getLabels(): array
{
    return ['name' => 'Meine Labels', ...];
}

// After: declare $labels directly
protected $labels = ['name' => 'Meine Labels', ...];
```

**Rector:** ⚠️ Manual

---

#### `unregisterExisting()` — moved to `ModelRegistrar`

**Why:** Unregistering a post type is part of the registration pipeline.

**Before:** Some themes called `$model->unregisterExisting()` directly.

**After:** This is handled automatically by the Registrar. Remove any direct calls.

**Rector:** ✅ Automated — direct calls are removed with a warning comment.

---

#### `$postTypes` on `Taxonomy` — typed property removed

**Why:** Same PHP 8.4 inheritance issue.

**Before:**
```php
class OrtTaxonomy extends Taxonomy
{
    protected array $postTypes = ['event', 'news'];
}
```

**After:**
```php
class OrtTaxonomy extends Taxonomy
{
    protected $postTypes = ['event', 'news'];
}
```

**Rector:** ✅ Automated via `composer sloth:migrate`

---

#### `$unique` on `Taxonomy` — typed property removed

**Before:**
```php
protected bool $unique = true;
```

**After:**
```php
protected $unique = true;
```

**Rector:** ✅ Automated via `composer sloth:migrate`

---

## Deprecations (not yet breaking)

These will become breaking in a future version. Fix them now to avoid issues later.

### `DIR_*` constants

`DIR_ROOT`, `DIR_APP`, `DIR_CACHE` etc. will be removed in Step 6. Use
`app()->path('app')`, `app()->path('cache')` etc. instead.

### `DS` constant

Use `/` directly or `DIRECTORY_SEPARATOR` where needed.

### `$GLOBALS['sloth']`

Use `app()` helper instead.

---


---

## Step 6 — Bootstrap & Config changes

### Salts — move from `salts.php` to `.env`

`salts.php` is deprecated and will be deleted by the installer on the next run.

WordPress salts should now be defined via `APP_SECRET` in `.env`. Sloth's
`bootstrap.php` derives all 8 salt constants automatically from `APP_SECRET`.

**Migration steps:**

1. Add `APP_SECRET` to `.env`:
   ```
   APP_SECRET=your-strong-random-secret-here
   ```

2. We recommend generating salts with:
   ```bash
   composer require rbdwllr/wordpress-salts-generator --dev
   vendor/bin/wpsalts dotenv
   ```
   This generates all 8 salts as individual `.env` entries — compatible with
   the new `bootstrap.php` which reads them via `env()` with `APP_SECRET` as fallback.

3. Delete `app/config/salts.php` — it is no longer used.

**Rector:** ⚠️ Manual — Rector cannot migrate secrets safely.

---

### `Configure::write()` / `Configure::read()` — migrate to `config()`

`Configure` is now a compatibility layer that proxies to Laravel's `config()`.
It will be removed in a future major version.

**Before:**
```php
Configure::write('theme.foo', 'bar');
$value = Configure::read('theme.foo');
```

**After:**
```php
config(['theme.foo' => 'bar']);
$value = config('theme.foo');
```

**Rector:** ✅ Automated via `composer sloth:migrate` (folivoro/shift)

Rule: `MigrateConfigureToLaravelConfigRector`
- `Configure::write('key', $value)` → `config(['key' => $value])`
- `Configure::read('key')` → `config('key')`
- `Configure::read('key', $default)` → `config('key', $default)`
- `Configure::check('key')` → `config()->has('key')`
- `Configure::delete('key')` → not directly supported — manual migration

---

### `bootstrap.php` — no longer managed by Sloth

`bootstrap.php` is now a project file, not a framework file. Sloth provides
a template but does not overwrite it after the initial install.

**What changed:**
- Salts derived from `APP_SECRET` — no more `salts.php`
- `Configure::boot()` removed — ENV vars available via `env()` directly
- `DIR_*` constants kept as deprecated compat layer — remove when ready
- `Core\Sloth::getInstance()` removed — use `Application::configure()->boot()`

**Long-term goal:** `bootstrap.php` only needs:
```php
require_once __DIR__ . '/vendor/autoload.php';
// Dotenv
// WordPress constants
```

Everything else is handled by Sloth on `after_setup_theme`.

---

---

## Twig filters — migrate from config to Extension classes

Defining Twig filters as anonymous closures in `app.config.php` via
`Configure::write('theme.twig.filters', [...])` is deprecated.

Closures cannot be tested, reused, or cached. The correct approach is
a proper Twig Extension class.

**Before (`app/config/app.config.php`):**
```php
Configure::write('theme.twig.filters', [
    new TwigFilter('obfuscate_email', function ($email) {
        // ...
    }),
]);
```

**After (`app/Twig/ThemeTwigExtension.php`):**
```php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ThemeTwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('obfuscate_email', $this->obfuscateEmail(...)),
        ];
    }

    public function obfuscateEmail(string $email, array $arguments = []): string
    {
        // ...
    }
}
```

Sloth autodiscovers all `AbstractExtension` subclasses in `app/Twig/`
and registers them automatically — no manual registration needed.

**Rector:** ⚠️ Manual — migrating anonymous closures to named methods
requires human judgement. Rector cannot infer meaningful method names
or understand the business logic inside closures.

**Steps:**
1. Create `app/Twig/ThemeTwigExtension.php` extending `AbstractExtension`
2. Move each closure to a named public method
3. Return `TwigFilter` instances referencing the methods
4. Remove the `Configure::write('theme.twig.filters', ...)` call


---

## Plugin autoactivation removed

Sloth no longer autoactivates WordPress plugins automatically.

**Before** — Sloth would activate plugins listed in Configure:
```php
Configure::write('plugins.autoactivate', true);
Configure::write('plugins.autoactivate.blacklist', ['cachify']);
```

**After** — use `alleyinteractive/wp-plugin-loader` for code-based plugin loading:

```bash
composer require alleyinteractive/wp-plugin-loader
```

Create `app/Providers/PluginServiceProvider.php` in your theme:

```php
namespace App\Providers;

use Alley\WP\WP_Plugin_Loader;
use Sloth\Core\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        WP_Plugin_Loader::create()
            ->add(config('plugins.active', []))
            ->when(fn() => app()->isLocal(), config('plugins.dev', []))
            ->load();
    }
}
```

And `app/config/plugins.php`:

```php
return [
    'active' => [
        'advanced-custom-fields-pro/acf.php',
        'yoast-seo/wp-seo.php',
    ],
    'dev' => [
        'query-monitor/query-monitor.php',
    ],
];
```

The plugin loader marks plugins as active in the WordPress admin UI
and supports conditional loading via `->when()`.

**Rector:** ⚠️ Manual

## Changelog

| Version | Branch | Breaking Changes |
|---------|--------|-----------------|
| Step 5  | `refactor/providers-cleanup` | `$layotter`, typed properties, registration methods moved |
| Step 6  | `refactor/zero-copy-install` | `DIR_*` constants, `DS`, `bootstrap.php` |
| Step 8  | `refactor/remove-corcel` | Corcel removed, Eloquent direct |
