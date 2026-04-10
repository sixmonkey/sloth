# Sloth Architecture Decisions

## Context

Sloth is a WordPress theme framework that has grown over several years.
The core ideas are solid, but the implementation evolved without a clear
structure. This document captures why we are changing what, and how.

---

## Plugin.php → ServiceProviders

### Problem
`Plugin.php` was a God Object that did everything: template rendering,
model loading, URL relativization, admin cleanup, pagination fix, route setup,
image sizes, plugin autoloading and more. ~500 lines, one class.

### Decision
Each responsibility gets its own ServiceProvider. `Plugin.php` becomes
a thin bootstrapper that only registers providers — analogous to `Sloth.php`.

### Providers and their responsibilities
- `AdminServiceProvider` — WP head cleanup, admin menu, update hiding
- `MediaServiceProvider` — image sizes, SVG upload, URL relativization
- `MenuServiceProvider` — nav menu registration
- `ModelServiceProvider` — model discovery, post type registration, Layotter setup
- `TaxonomyServiceProvider` — taxonomy discovery and registration
- `ApiServiceProvider` — REST controller autodiscovery and route registration
- `TemplateServiceProvider` — Brain Hierarchy template discovery, context, rendering

### Order in Plugin.php
`TemplateServiceProvider` comes last — it reads `sloth.models` and
`sloth.taxonomies` from the container, which are only populated by the
Model and TaxonomyServiceProviders beforehand.

### Model and Taxonomy registry in the container
`ModelServiceProvider` and `TaxonomyServiceProvider` write their registered
classes as `sloth.models` and `sloth.taxonomies` into the container.
This allows other providers to access them without direct coupling.

---

## Sloth.php vs Plugin.php — two entry points

### Why two classes?

The separation is intentional and correct — but poorly communicated.

**`Sloth.php`** boots *before* WordPress. It is pure framework infrastructure:
building the container, registering service providers, setting up facades,
establishing the Corcel DB connection. Always runs, regardless of theme or plugin.

**`Plugin.php`** boots *after* WordPress. It requires a running WP installation
(`is_blog_installed()`), reads theme paths (`get_template_directory()`),
hooks into WP actions. This is theme integration, not framework.

```
bootstrap.php → $GLOBALS['sloth'] = Sloth::getInstance()         ← Framework
sloth.php     → $GLOBALS['sloth::plugin'] = Plugin::getInstance() ← Theme
```

### The real problem: globals as a communication channel

What does not work: `Plugin` accesses the container via `$GLOBALS['sloth']`,
and the rest of the framework accesses `Plugin` back via `$GLOBALS['sloth::plugin']`:

```php
// In Module.php, MenuItem.php, ACFHelper.php, SlothBarPanel.php:
$GLOBALS['sloth::plugin']->getContext()
$GLOBALS['sloth::plugin']->getAllModels()
$GLOBALS['sloth::plugin']->isDevEnv()
$GLOBALS['sloth::plugin']->getCurrentTemplate()

// In Scaffolder.php, SlothTwigExtension.php, Layotter.php:
$GLOBALS['sloth']->container->...
```

This is mutual dependency through globals — not through clean injection.
Consequences:
- Not testable (globals cannot be mocked)
- Order-dependent (Plugin must exist before everything else)
- Hidden coupling (who calls what when is unclear)

### Goal
`getContext()`, `getAllModels()`, `getCurrentTemplate()` etc. should be
accessible via the container, not via `$GLOBALS['sloth::plugin']`.
`TemplateServiceProvider` registers its state in the container —
other services retrieve it from there.

```php
// Instead of:
$GLOBALS['sloth::plugin']->getContext()

// Going forward:
$this->app['sloth.context']
```

---

## Model extends Corcel\Model instead of Corcel\Post

### Problem
`Corcel\Post` uses the `AdvancedCustomFields` trait which references
`Corcel\Acf\AdvancedCustomFields` from the optional `corcel/acf` package —
without it being a declared dependency. Fatally crashes on first `->acf`
access if the package is not installed.

### Decision
`Sloth\Model\Model` extends `Corcel\Model` (the thin Eloquent wrapper)
directly instead of `Corcel\Post`. We explicitly pull in only what we need:

- `MetaFields` — `$this->meta->...` access
- `OrderScopes` — `->newest()`, `->oldest()` etc.
- `CustomTimestamps` — `post_date`/`post_modified` mapping
- `HasACF` — our own trait, replaces Corcel's ACF integration

Not included: `AdvancedCustomFields`, `Shortcodes`, `Aliases`.

### static::class instead of Post::class in relations
All relations (`parent`, `children`, `attachment`, `revision`) use
`static::class` instead of `Post::class`. Reason: subclasses have their own
mutators (`getContentAttribute` with `apply_filters` etc.) — with `Post::class`
generic instances without these mutators would be returned. Especially
relevant for `revision()` and `loadPreview()`.

---

## Model classes as single source of truth for post types

### Decision
The existence of a model class corresponds to a WordPress post type.
Configuration (`$names`, `$options`, `$labels`, `$icon`, `$admin_columns`)
stays in the model — this is intentional, not messy.

### Why configuration stays in the model
Backwards compatibility: existing subclasses do not need to be touched.
And semantically correct: the model *is* the post type, its configuration
belongs there.

### Registration is extracted
The actual WP registration logic (`PostType`, columns, Layotter setup)
does not belong in the model — that is the `ModelServiceProvider`'s job.
The model knows its configuration, the provider knows how to register
a post type from it.

---

## Testing strategy

### Tools
- **Pest** — testing, more expressive than PHPUnit
- **PHPStan** — static analysis, type safety
- **PHP CS Fixer** — code style, automated
- **Rector** — code modernization, PHP 8.2 patterns
- **Infection** — mutation testing, test quality

### Order of introduction
1. **Rector first** — one-time, separate commit, modernizes existing code
   before we start restructuring. Dry-run first, then commit. Cleanly
   separated from structural changes.
2. **PHPStan baseline** — mark the current state as known,
   new errors from this point onward will fail.
3. **CS Fixer** — runs before every commit from now on.
4. **Pest** — new providers are written with tests directly.
5. **Infection** — once enough tests exist.

### WordPressAdapter (not yet implemented)
WP functions (`is_single()`, `get_queried_object()` etc.) are global
functions — not directly mockable. The plan: extract a `WordPressAdapter`
that serves as a seam between Sloth logic and WP globals.
Can then be replaced by a fake implementation in tests.

### Test-first refactoring
Write tests for `Plugin.php` *before* refactoring. The tests are the
safety net that guarantees nothing breaks while extracting providers.

---

## Renaming: Sloth → Container, Plugin → ThemeBootstrapper

### Problem
`Sloth` and `Plugin` are not good class names — too generic, zero semantics.
`Sloth` is a container, `Plugin` bootstraps a theme.

### Decision: deprecation without breaking change

The actual logic moves into renamed classes. The old names remain as
empty shells with `@deprecated`:

```php
// Sloth/Core/Container.php — the actual class
class Container extends Singleton { ... }

// Sloth/Core/Sloth.php — empty shell, kept for backwards compatibility
/** @deprecated Use Sloth\Core\Container instead */
class Sloth extends Container {}
```

```php
// Sloth/Plugin/ThemeBootstrapper.php — the actual class
class ThemeBootstrapper extends Singleton { ... }

// Sloth/Plugin/Plugin.php — empty shell, kept for backwards compatibility
/** @deprecated Use Sloth\Plugin\ThemeBootstrapper instead */
class Plugin extends ThemeBootstrapper {}
```

### Migration path
Existing sites continue to work without changes:
```php
Sloth::getInstance()       // still works, PHPStan warns
Plugin::getInstance()      // still works, PHPStan warns
$GLOBALS['sloth']          // still works
$GLOBALS['sloth::plugin']  // still works
```

New sites and refactored parts use the new names:
```php
Container::getInstance()
ThemeBootstrapper::getInstance()
```

### Why this is good
- No breaking change for production sites
- PHPStan sees `@deprecated` and warns on new code
- Rector can migrate automatically when we are ready
- The names finally say what the classes actually do

---

## Singleton — long-term goal

### Problem
`Singleton.php` is not a Laravel pattern. Laravel containers manage
lifetime — classes do not do that themselves. Singletons are also
hard to test because static state cannot be reset between tests.

### Now
`Sloth::getInstance()` and `Plugin::getInstance()` remain untouched for now.
The deprecated shells (`Sloth extends Container`,
`Plugin extends ThemeBootstrapper`) are the first step.

### Not now: getInstance() as a container shim
The idea of redirecting `getInstance()` internally to `app()` sounds
tempting — but it is a lazy compromise. Anyone reading `getInstance()`
still thinks "Singleton", regardless of what happens internally.
That hides the problem instead of solving it.

### Long-term goal
Once Rector, ServiceProviders and tests are stable — dedicated branch
`refactor/remove-singleton`:

```php
// Instead of:
Sloth::getInstance()->container['route']->dispatch();

// Going forward:
app(Container::class)->container['route']->dispatch();

// Or via Facade:
SlothContainer::dispatchRouter();
```

`Singleton.php` will then be deprecated itself and die quietly.
But that is step 9, not step 1.

---

## What gets removed

### AdminServiceProvider — significantly slimmed down

After analysing actual usage: much of `AdminServiceProvider` is opinionated
theme code that does not belong in a framework.

**Removed entirely:**

- `obfuscateWP()` — an opinion about which WP head tags should be removed.
  That is a theme decision, not a framework decision. Goes in the theme's
  `functions.php` if desired.
- `autoloadPlugins()` — dangerous (activates all installed plugins),
  very opinionated, not a framework feature.
- `trackDataChange()` — dev helper that writes a file to cache.
  Too specific for a framework.
- `fixNetworkAdminUrl()` — very specific hack for a particular server
  configuration. Does not belong in a framework.
- `replaceHomeUrl()` — never called anywhere. Dead code.

**Stays — because it is actually a framework feature:**

- `hideUpdates()` — configurable via `Configure`, makes sense as an
  opt-in framework feature.
- `cleanupAdminMenu()` — generic enough, never harmful.
- `addLayotterStyles()` — Layotter-specific, stays as long as Layotter
  is part of the framework.

**Result:** `AdminServiceProvider` shrinks to ~40 lines.
Everything else is the theme's responsibility.

### What is actually used externally

Analysis of `$GLOBALS['sloth::plugin']` accesses shows: only these
Plugin methods are used outside of `Plugin.php`:

| Method | Used by |
|---|---|
| `getContext()` | `Module.php`, `MenuItem.php` |
| `getAllModels()` | `MenuItem.php` |
| `getPostTypeClass()` | `Module.php` |
| `isDevEnv()` | `ACFHelper.php` |
| `getCurrentTemplate()` | `SlothBarPanel.php` |

These are the only methods that need a public API —
and even those should be accessible via the container going forward,
not via `$GLOBALS['sloth::plugin']`.

---

## Git workflow

### Branch strategy
```
develop (current state, always deployable)
    └── refactor/rector-modernize
        └── fix/acf-trait-namespace
            └── refactor/corcel-model
                └── refactor/rename-classes
                    └── refactor/service-providers
                        └── refactor/globals-to-container
                            └── refactor/acf-service-provider
                                └── refactor/testing
                                    └── refactor/remove-singleton
```

Each branch is self-contained. `develop` is always deployable.

### Commit discipline
One commit = one thing. Never mix structural changes and style fixes.
Always `git add -p` instead of `git add .` — forces reviewing each hunk
individually and prevents half-finished work from ending up in commits.

### CI
On every push: Pest, PHPStan, CS Fixer. No merge without green CI.

---

## Work plan

Each step is its own branch. Each branch is self-contained and
deployable before the next one begins.

### Step 1 — `refactor/rector-modernize`
Run Rector once over `src/`. Dry-run first, then commit.
No behavior changes — only syntax modernization.

```bash
composer require --dev rector/rector
./vendor/bin/rector process src --dry-run
./vendor/bin/rector process src
git add -p
git commit -m "rector: modernize to PHP 8.2"
```

### Step 2 — `fix/acf-trait-namespace`
`HasACF` imports `App\Model\User` — a framework must not depend on the theme.
Switch to `Sloth\Model\User`.

```php
// Before — wrong:
use App\Model\User;

// After:
use Sloth\Model\User;
```

### Step 3 — `refactor/corcel-model`
Switch `Sloth\Model\Model` from `Corcel\Post` to `Corcel\Model`.
Explicitly pull in required traits, remove `AdvancedCustomFields` and `Shortcodes`.
Ensure `static::class` is used in all relations.

### Step 4 — `refactor/rename-classes`
- `Core/Sloth.php` → logic moves to `Core/Container.php`, `Sloth` becomes empty shell
- `Plugin/Plugin.php` → logic moves to `Plugin/ThemeBootstrapper.php`, `Plugin` becomes empty shell
- Rebuild PHPStan baseline

### Step 5 — `refactor/service-providers`
Extract `ThemeBootstrapper` into ServiceProviders.
Extract one provider at a time, verify the site still works after each one.
Order:

1. `AdminServiceProvider` — no external state, easiest starting point
2. `MediaServiceProvider` — also isolated
3. `MenuServiceProvider` — trivial
4. `TaxonomyServiceProvider`
5. `ModelServiceProvider` — populates `sloth.models` in the container
6. `ApiServiceProvider`
7. `TemplateServiceProvider` — last, depends on `sloth.models` and `sloth.taxonomies`

### Step 6 — `refactor/globals-to-container`
Replace `$GLOBALS['sloth::plugin']` accesses with container keys.
Affects: `Module.php`, `MenuItem.php`, `ACFHelper.php`, `SlothBarPanel.php`.

```php
// Before:
$GLOBALS['sloth::plugin']->getContext()
$GLOBALS['sloth::plugin']->getAllModels()
$GLOBALS['sloth::plugin']->isDevEnv()

// After:
app('sloth.context')
app('sloth.models')
app()->environment() === 'development'
```

### Step 7 — `refactor/acf-service-provider`
`ACFHelper` is a Singleton that is manually booted.
Becomes `AcfServiceProvider` — registers itself,
no manual boot needed.

### Step 8 — `refactor/testing`
Introduce `WordPressAdapter`. First tests for the critical paths:
`TemplateServiceProvider::buildContext()`, `ModelServiceProvider::loadModels()`,
`MediaServiceProvider` URL logic.

### Step 9 — `refactor/remove-singleton` (long-term)
Only once all previous steps are stable and tested.
`Singleton.php` deprecated, `getInstance()` removed,
everything runs through `app()`.
