<p align="center">
<a href="https://folivoro.com" target="_blank">
<img src="https://raw.githubusercontent.com/folivoro/art/refs/heads/main/sloth-logo.svg" alt="Sloth Logo" width="200" height="200" />
</a>
</p>
<p align="center">
<a href="https://packagist.org/packages/sixmonkey/sloth"><img src="https://img.shields.io/packagist/dt/sixmonkey/sloth" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/sixmonkey/sloth"><img src="https://img.shields.io/packagist/v/sixmonkey/sloth" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/sixmonkey/sloth"><img src="https://img.shields.io/packagist/l/sixmonkey/sloth" alt="License"></a>
<a href="https://github.com/sixmonkey/sloth/actions/workflows/ci.yml"><img src="https://github.com/sixmonkey/sloth/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
</p>

# Sloth — WordPress Theme Framework

A modern WordPress theme framework built with Laravel components, designed for developers who want to build powerful
WordPress themes with a clean, object-oriented architecture.

## Features

- **Laravel Components**: Container, Validation, Pagination, View, Cache, Events and more — without the full Laravel
  stack
- **Symfony Routing**: Laravel-esque custom routing backed by `symfony/routing` — zero extra dependencies
- **HTTP Layer**: `Response::make()`, `Response::json()`, `Response::redirect()` and more
- **ACF Support**: Seamless integration with Advanced Custom Fields
- **Module System**: Organized module-based architecture for theme components
- **Template Hierarchy**: `brain/hierarchy` integration for intelligent template loading
- **WordPress REST API**: Easy API endpoint creation with auto-discovery
- **DebugBar**: PHP DebugBar integration for development
- **WP-CLI**: Artisan-style console commands via `wp sloth`

## Requirements

- **PHP**: 8.4 or higher
- **WordPress**: 5.0 or higher
- **Composer**: 2.0 or higher

## Installation

```bash
composer create-project sixmonkey/sloth my-theme
```

## Quick Start

### 1. Theme Activation

Activate the theme in WordPress admin. Sloth automatically bootstraps and registers all service providers.

### 2. Defining Routes

Create `app/routes/web.php` or `theme/routes/web.php`:

```php
<?php

use Sloth\Facades\Route;
use Sloth\Http\Response;

// Basic route
Route::get('/css/products', function () {
    return Response::make(view('styles.index'), 200)
        ->header('Content-Type', 'text/css');
})->name('product-styles');

// Route with parameters
Route::get('/posts/{slug}', function (string $slug) {
    return Response::make(view('single', compact('slug')));
})->name('post.show');

// JSON response
Route::get('/api/status', function () {
    return Response::json(['status' => 'ok']);
});

// Redirect
Route::get('/old-path', function () {
    Response::redirect('/new-path');
});
```

Custom routes run on `template_redirect` at priority 1 — before WordPress template loading. If no route matches,
WordPress handles the request normally.

> **Note:** Custom routes are for non-WordPress URLs like `/css/products`, `/sitemap.xml`, or `/api/custom`. Do not
> register routes that conflict with WordPress template URLs unless you intentionally want to override them.

### 3. Using Models

```php
<?php

use App\Model\NewsModel;

// Get a post by ID
$post = NewsModel::find(123);

// Query posts
$posts = NewsModel::published()
    ->orderBy('post_date', 'DESC')
    ->limit(10)
    ->get();
```

### 4. Using the View System

```php
<?php

use Sloth\Facades\View;

// Render a Twig view
View::make('partials.header', ['title' => 'Welcome']);
```

### 5. Validation

```php
<?php

use Illuminate\Support\Facades\Validator;

$validator = Validator::make($data, [
    'name'  => 'required|min:3|max:255',
    'email' => 'required|email',
]);

if ($validator->fails()) {
    $errors = $validator->errors();
}
```

## Routing

Sloth's router is backed by `symfony/routing` and provides a Laravel-esque API. Routes are loaded from
`app/routes/web.php` and `theme/routes/web.php` — both are optional.

### HTTP Methods

```php
Route::get('/path', fn() => Response::make('hello'));
Route::post('/path', fn() => Response::json(['ok' => true]));
Route::put('/path', fn() => Response::noContent());
Route::delete('/path', fn() => Response::noContent());
```

### Route Parameters

```php
Route::get('/posts/{slug}', function (string $slug) {
    return Response::make(view('single', compact('slug')));
});

Route::get('/archive/{year}/{month}', function (string $year, string $month) {
    return Response::make(view('archive', compact('year', 'month')));
});
```

### Named Routes & URL Generation

```php
Route::get('/posts/{slug}', fn($slug) => Response::make(view('single')))->name('post.show');

// Generate URL
$url = app('router')->url('post.show', ['slug' => 'hello-world']);
// → /posts/hello-world
```

## HTTP Response

`Sloth\Http\Response` extends `Illuminate\Http\Response` with static factory methods.

```php
use Sloth\Http\Response;

// HTML response
Response::make('<h1>Hello</h1>', 200);
Response::make('<h1>Hello</h1>', 200, ['Content-Type' => 'text/html']);

// JSON response
Response::json(['key' => 'value']);
Response::json(['error' => 'Not found'], 404);

// No content
Response::noContent();
Response::noContent(205);

// File download
Response::download('/path/to/file.pdf', 'download.pdf');

// Inline file (display in browser)
Response::file('/path/to/file.pdf');

// Redirect (uses wp_redirect() when available)
Response::redirect('/new-path');
Response::redirect('/new-path', 301);
```

All methods support chaining for headers:

```php
Response::make(view('styles.index'), 200)
    ->header('Content-Type', 'text/css')
    ->header('Cache-Control', 'public, max-age=3600');
```

## Configuration

### Database

Sloth reads database configuration from `database` config key. WordPress constants (`DB_HOST`, `DB_NAME` etc.) are used
as fallbacks. Override in `app/config/database.php`:

```php
<?php

return [
    'default' => 'wordpress',

    'connections' => [
        'wordpress' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', DB_HOST),
            'database'  => env('DB_NAME', DB_NAME),
            'username'  => env('DB_USER', DB_USER),
            'password'  => env('DB_PASSWORD', DB_PASSWORD),
            'prefix'    => env('DB_PREFIX', DB_PREFIX),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],

        // Add extra connections
        'external' => [
            'driver'   => 'mysql',
            'host'     => env('EXTERNAL_DB_HOST'),
            'database' => env('EXTERNAL_DB_NAME'),
            'username' => env('EXTERNAL_DB_USER'),
            'password' => env('EXTERNAL_DB_PASSWORD'),
            'prefix'   => '',
            'charset'  => 'utf8mb4',
            'collation'=> 'utf8mb4_unicode_ci',
        ],
    ],
];
```

Use a specific connection in a model:

```php
class ExternalModel extends Model
{
    protected $connection = 'external';
}
```

### Environment Variables

```env
APP_ENV=local
APP_DEBUG=true
DB_HOST=localhost
DB_NAME=wordpress
DB_USER=root
DB_PASSWORD=
DB_PREFIX=wp_
```

### Custom Configuration

Add files to `app/config/` — each filename becomes a config key:

```php
// app/config/theme.php
return [
    'colors' => ['primary' => '#ff0000'],
];

// Access via
config('theme.colors.primary');
```

## Auto-Discovery: Convention over Configuration

Sloth automatically discovers and registers classes in convention-based directories.

### `app/` vs `theme/`

| Path     | Scope                                     | When to use                                  |
|----------|-------------------------------------------|----------------------------------------------|
| `app/`   | Always loaded, regardless of active theme | Shared models, APIs, reusable components     |
| `theme/` | Only loaded for the active theme          | UI, presentation logic, theme-specific hooks |

### Recommended Structure

| Component           | Location                                       | Why                                                   |
|---------------------|------------------------------------------------|-------------------------------------------------------|
| **Models**          | `app/Model/`                                   | Data structure — theme-independent                    |
| **Taxonomies**      | `app/Taxonomy/`                                | Data structure — belongs with models                  |
| **Routes**          | `app/routes/web.php` or `theme/routes/web.php` | App-wide or theme-specific routes                     |
| **Modules**         | `theme/Module/`                                | UI components — always theme-specific                 |
| **API Controllers** | Both                                           | General APIs in `app/`, theme-specific in `theme/`    |
| **Providers**       | Both                                           | Framework services in `app/`, theme hooks in `theme/` |
| **Includes**        | Both                                           | Shared helpers in `app/`, theme functions in `theme/` |

### Models (Custom Post Types)

```php
<?php
// app/Model/NewsModel.php

namespace App\Model;

use Sloth\Model\Model;

class NewsModel extends Model
{
    protected $postType = 'news';

    protected $options = [
        'public'       => true,
        'show_in_rest' => true,
        'menu_icon'    => 'dashicons-admin-post',
    ];

    protected $names = [
        'singular' => 'News',
        'plural'   => 'News',
        'slug'     => 'news',
    ];
}
```

**What happens automatically:**

- Post type `news` is registered via `register_extended_post_type()`
- Model is available for Eloquent queries (`NewsModel::all()`)

**To disable registration:**

```php
protected $register = false;
```

### Taxonomies

```php
<?php
// app/Taxonomy/CategoryTaxonomy.php

namespace App\Taxonomy;

use Sloth\Model\Taxonomy;

class CategoryTaxonomy extends Taxonomy
{
    protected $slug = 'category';
    protected $postTypes = ['news', 'post'];
    protected $unique = false;
    protected $names = [
        'singular' => 'Category',
        'plural'   => 'Categories',
    ];
}
```

### Modules

```php
<?php
// theme/Module/TeaserModule.php

namespace Theme\Module;

use Sloth\Module\Module;

class TeaserModule extends Module
{
    public $json = ['params' => ['id']];
}
```

**What happens automatically:**

- Available in Twig: `{% include 'module/teaser' %}`
- REST endpoint: `GET /sloth/v1/module/teaser[/{id}]`

### Service Providers

```php
<?php
// theme/Providers/ThemeProvider.php

namespace Theme\Providers;

use Sloth\Core\ServiceProvider;

class ThemeProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('my-service', fn() => new MyService());
    }

    public function boot(): void
    {
        // Boot-time setup
    }

    public function getHooks(): array
    {
        return [
            'init' => fn() => $this->doSomething(),
            'admin_menu' => ['callback' => fn() => $this->addMenu(), 'priority' => 20],
        ];
    }

    public function getFilters(): array
    {
        return [
            'the_content' => fn(string $c) => $this->transform($c),
        ];
    }
}
```

### API Controllers

```php
<?php
// app/Api/NewsController.php

namespace App\Api;

use Sloth\Api\Controller;

class NewsController extends Controller
{
    public function index()
    {
        return NewsModel::published()->get();
    }

    public function single($id)
    {
        return NewsModel::find($id);
    }
}
```

**What happens automatically:**

- `index()` → `GET /wp-json/sloth/v1/news`
- `single()` → `GET /wp-json/sloth/v1/news/{id}`

### Includes

Any `.php` file in `app/Includes/` or `theme/Includes/` is automatically `require_once`'d during boot.

## Service Provider Hooks

### Registering Actions

```php
public function getHooks(): array
{
    return [
        // Single callback (priority 10)
        'init' => fn() => $this->registerPostTypes(),

        // With explicit priority
        'admin_menu' => ['callback' => fn() => $this->addMenu(), 'priority' => 20],

        // Multiple callbacks
        'wp_loaded' => [
            ['callback' => fn() => $this->early(), 'priority' => 5],
            ['callback' => fn() => $this->late(), 'priority' => 20],
        ],
    ];
}
```

### Registering Filters

```php
public function getFilters(): array
{
    return [
        'the_title' => fn(string $title) => '★ ' . $title,

        'body_class' => [
            'callback' => fn(array $classes) => [...$classes, 'my-class'],
            'priority' => 20,
        ],
    ];
}
```

### When to Use EventBridge Instead

For shared hooks that multiple components might listen to, use the EventBridge in `boot()`:

```php
use Sloth\Event\WpHookFired;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::listen('wp:the_content', function (WpHookFired $event) {
        $event->result = transform($event->result);
    });
}
```

## WordPress Event Bridge

Sloth bridges WordPress hooks to the Laravel event system.

### Listening to Actions

```php
Event::listen('wp:wp_loaded', function (WpHookFired $event) {
    // WordPress fully loaded
});
```

### Modifying Filters

```php
Event::listen('wp:body_class', function (WpHookFired $event) {
    $event->result = [...(array) $event->result, 'my-class'];
});
```

### Available Hooks

| Hook                | Type   | Phase                   |
|---------------------|--------|-------------------------|
| `muplugins_loaded`  | action | MU-plugins loaded       |
| `plugins_loaded`    | action | All plugins loaded      |
| `after_setup_theme` | action | Theme loaded            |
| `init`              | action | WordPress initialized   |
| `wp_loaded`         | action | Full WordPress setup    |
| `template_redirect` | action | Before template loading |
| `wp_head`           | action | Inside `<head>`         |
| `wp_footer`         | action | Before `</body>`        |
| `the_content`       | filter | Post content            |
| `the_title`         | filter | Post title              |
| `the_excerpt`       | filter | Post excerpt            |
| `body_class`        | filter | Body classes            |
| `shutdown`          | action | PHP shutdown            |

### Dynamic Hook Registration

```php
$bridge = app(WordPressEventBridge::class);
$bridge->addHook('save_post', 'action');

Event::listen('wp:save_post', function (WpHookFired $event) {
    $postId = $event->firstArg();
});
```

## Directory Structure

```
sloth/
├── src/
│   ├── ACF/            # ACF integration
│   ├── Admin/          # WordPress admin
│   ├── Api/            # REST API controllers
│   ├── Cache/          # Cache layer
│   ├── Configure/      # Legacy config compat
│   ├── Console/        # WP-CLI commands
│   ├── Context/        # View context
│   ├── Core/           # Container, Application, ServiceProvider
│   ├── Database/       # Eloquent/Capsule setup
│   ├── Debug/          # DebugBar integration
│   ├── Event/          # WordPress EventBridge
│   ├── Exceptions/     # Exception handling
│   ├── Facades/        # Facade classes
│   ├── Filesystem/     # File system helpers
│   ├── Http/           # Response, HttpServiceProvider
│   ├── LayotterBridge/ # Layotter page builder integration
│   ├── Media/          # Media handling
│   ├── Model/          # Eloquent models + registrars
│   ├── Module/         # Module system
│   ├── Pagination/     # Pagination
│   ├── Routing/        # Route, Router, RoutingServiceProvider
│   ├── Support/        # Manifests, utilities
│   ├── Template/       # WordPress template hierarchy
│   ├── Theme/          # Theme bootstrapping
│   ├── Translation/    # i18n
│   ├── Validation/     # Form validation
│   └── View/           # Twig view rendering
├── tests/              # Pest test suite
├── composer.json
└── phpunit.xml
```

## Available Facades

| Facade       | Description                                        |
|--------------|----------------------------------------------------|
| `Route`      | Custom routing (`Route::get/post/put/delete`)      |
| `Response`   | HTTP responses (`Response::make/json/redirect`)    |
| `View`       | Twig template rendering                            |
| `Cache`      | Laravel cache                                      |
| `Validation` | Form validation                                    |
| `Configure`  | Legacy config access (deprecated — use `config()`) |
| `File`       | Filesystem operations                              |

## WP-CLI Commands

```bash
# List commands
wp sloth list

# Welcome message
wp sloth inspire

# Clear manifest cache
wp sloth manifest:clear

# Get a config value
wp sloth config:get app.env
```

### Creating Custom Commands

```php
<?php
// app/Console/Commands/MyCommand.php

namespace App\Console\Commands;

use Sloth\Console\Command;
use function Termwind\render;

class MyCommand extends Command
{
    protected $signature = 'my:command {name?}';
    protected $description = 'My custom command';

    public function handle(): int
    {
        render('<div class="text-green-500">Hello ' . $this->argument('name') . '!</div>');
        return self::SUCCESS;
    }
}
```

Commands are auto-discovered from `src/Console/Commands/`, `app/Console/Commands/`, and `theme/Console/Commands/`.

## Development

```bash
# Run tests
composer test

# Static analysis
composer analyse

# Code style check
composer cs-check

# Code style fix
composer cs-fix
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

MIT — see [LICENSE](LICENSE).

## Contributors
<a href="https://github.com/sixmonkey/sloth/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=sixmonkey/sloth" />
</a>

- [Ben Kremer](https://benkremer.de)
- [Jochen Wichmann](https://github.com/jochenwichmann)
- [Max Leistner](https://maxleistner.de)
- [Christof Hardt](https://christof-hardt.de/)
- [Christian Müller-Brinkum](https://github.com/cmbsvk)
- [Danielle Hardt](https://github.com/daniellehardt)

## Links

- [Documentation](https://sixmonkey.github.io/sloth)
- [Issue Tracker](https://github.com/sixmonkey/sloth/issues)
- [Packagist](https://packagist.org/packages/sixmonkey/sloth)
