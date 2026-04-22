<p align="center">
<a href="https://folivoro.com" target="_blank">
<img src="https://raw.githubusercontent.com/folivoro/art/refs/heads/main/sloth-logo.svg" alt="Sloth Logo" width="200" height="200" />
</a>
</p>
<p align="center">
<a href="https://packagist.org/packages/sixmonkey/sloth"><img src="https://img.shields.io/packagist/dt/sixmonkey/sloth" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/sixmonkey/sloth"><img src="https://img.shields.io/packagist/v/sixmonkey/sloth" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/sixmonkey/sloth"><img src="https://img.shields.io/packagist/l/sixmonkey/sloth" alt="License"></a>
</p>

# Sloth - WordPress Theme Framework

A modern WordPress theme framework built with Laravel components, designed for developers who want to build powerful
WordPress themes with a clean, object-oriented architecture.

## Features

- **Laravel Integration**: Leverages popular Laravel components (Container, Validation, Pagination, View, etc.)
- **ACF Support**: Seamless integration with Advanced Custom Fields via Corcel
- **Flexible Routing**: Custom routing system with FastRoute integration
- **Module System**: Organized module-based architecture for theme components
- **Template Hierarchy**: Brain Hierarchy integration for intelligent template loading
- **WordPress REST API**: Easy API endpoint creation
- **Debugging**: Tracy integration for advanced debugging
- **Scaffolding**: Generate modules, templates, and components with CLI tools

## Requirements

- **PHP**: 8.2 or higher
- **WordPress**: 5.0 or higher
- **Composer**: 2.0 or higher

## Installation

### Via Composer

```bash
composer create-project sixmonkey/sloth my-theme
```

### Manual Installation

1. Clone or download this repository into your WordPress theme directory
2. Run `composer install` to install dependencies
3. Configure your theme as needed

## Quick Start

### 1. Theme Activation

Activate the theme in your WordPress admin panel. The framework will automatically bootstrap and register all service
providers.

### 2. Creating a Module

Use the scaffolder to create a new module:

```bash
php sloth-cli.php make:module MyModule
```

This creates:

- `src/Module/MyModule/`
- `src/Module/MyModule/Module.php`
- `src/_view/Module/my-module/`
- SCSS files and ACF configuration

### 3. Defining Routes

Create a `routes.php` file in your theme root:

```php
<?php

use Sloth\Facades\Route;

// Basic route
Route::get('/about', [
    'controller' => 'PageController',
    'action' => 'about',
]);

// With parameters
Route::get('/blog/{slug}', [
    'controller' => 'BlogController',
    'action' => 'show',
]);
```

### 4. Creating Controllers

Controllers go in `Theme/Controller/`:

```php
<?php

namespace Theme\Controller;

use Sloth\Controller\Controller;

class PageController extends Controller
{
    public function about(): void
    {
        $this->view('about', [
            'title' => 'About Us',
        ]);
    }
}
```

### 5. Using Models

```php
<?php

use Sloth\Model\Post;

// Get a post by ID
$post = Post::find(123);

// Get posts by category
$posts = Post::where('category', 'news')
    ->orderBy('date', 'DESC')
    ->limit(10)
    ->get();

// Custom post type
$projects = \Sloth\Model\Post::type('project')
    ->status('publish')
    ->get();
```

### 6. Using the View System

```php
<?php

use Sloth\Facades\View;

// Render a view
View::make('partials.header', ['title' => 'Welcome']);

// With layout
View::make('content.page')
    ->layout('layouts.main')
    ->with(['title' => 'Page Title']);
```

### 7. Validation

```php
<?php

use Sloth\Facades\Validation;

$validator = Validation::make($request->all(), [
    'name'  => 'required|min:3|max:255',
    'email' => 'required|email',
    'url'   => 'url|nullable',
]);

if ($validator->fails()) {
    $errors = $validator->errors();
}
```

## Configuration

### Environment Variables

Create a `.env` file in your theme root:

```env
APP_ENV=local
WP_DEBUG=true
DATABASE_HOST=localhost
DATABASE_NAME=wordpress
DATABASE_USER=root
DATABASE_PASSWORD=
```

### Custom Configuration

Add configuration files in `src/config/`:

```php
<?php

return [
    'setting_name' => 'value',
    'another_setting' => true,
];
```

Access configuration via:

```php
<?php

use Sloth\Facades\Configure;

$value = Configure::get('config_file.setting_name');
```

## Directory Structure

```
sloth/
├── src/
│   ├── Core/           # Core framework classes
│   ├── Model/          # WordPress model extensions
│   ├── Route/          # Routing system
│   ├── View/           # View system
│   ├── Controller/     # Base controllers
│   ├── Facades/        # Facade classes
│   ├── Module/         # Module system
│   ├── Field/          # Custom ACF fields
│   ├── _view/          # Twig templates
│   └── config/         # Configuration files
├── tests/              # PHPUnit tests
├── docs/               # Additional documentation
├── composer.json       # Dependencies
└── phpunit.xml         # Test configuration
```

## Available Facades

| Facade       | Description          |
|--------------|----------------------|
| `Route`      | Routing system       |
| `View`       | Template rendering   |
| `Validation` | Form validation      |
| `Configure`  | Configuration access |
| `Pagination` | Pagination helper    |
| `Module`     | Module management    |
| `Menu`       | Menu system          |
| `Customizer` | WordPress Customizer |
| `Deployment` | Deployment helpers   |
| `Layotter`   | Page builder         |

## WP-CLI Commands

Sloth provides Artisan-style console commands available via `wp sloth`:

```bash
# List all available commands
wp sloth list

# Display a welcome message
wp sloth inspire

# Clear all manifest files
wp sloth manifest:clear
```

### Available Commands

| Command | Description |
|---------|-------------|
| `wp sloth inspire` | Display a welcome message |
| `wp sloth manifest:clear` | Clear all Sloth manifest files |
| `wp sloth list` | List all available commands |

### Creating Custom Commands

Create commands in your theme's `app/Console/Commands/` directory:

```php
<?php

namespace App\Console\Commands;

use Sloth\Console\Command;
use function Termwind\render;

class MyCommand extends Command
{
    protected $signature = 'my:command';

    protected $description = 'My custom command';

    public function handle(): int
    {
        // Use Termwind for beautiful output
        render('<div class="text-green-500">Hello from my command!</div>');

        // Or use standard Laravel output methods
        $this->info('Info message');
        $this->warn('Warning message');
        $this->error('Error message');

        return self::SUCCESS;
    }
}
```

Commands are auto-discovered from:
- `src/Console/Commands/` - Framework commands
- `app/Console/Commands/` - Theme/app commands
- `theme/Console/Commands/` - Theme commands (legacy location)

### Command Structure

Extend `Sloth\Console\Command` (which extends `Illuminate\Console\Command`) to get:
- Full Laravel Artisan command features
- Termwind support for beautiful CLI output
- Access to the Sloth container via `$this->app`

```php
use Sloth\Console\Command;

class MyCommand extends Command
{
    protected $signature = 'my:command {arg?}. {--option= : An option}';

    public function handle(): int
    {
        // Get arguments and options
        $arg = $this->argument('arg');
        $option = $this->option('option');

        // Use Sloth services
        $cache = app('cache');

        $this->info('Done!');
        return self::SUCCESS;
    }
}
```

### Termwind CLI Styling

Use [Termwind](https://termwind.com) to create beautiful CLI output with Tailwind-like classes:

```php
use function Termwind\render;

render(<<<'HTML'
    <div class="mt-2">
        <div class="text-center text-gray-500">
            <span class="bg-green-400 text-black px-2">Success!</span>
        </div>
    </div>
```

## Development

### Running Tests

```bash
composer test
```

### Static Analysis

```bash
composer analyse
```

### Code Style

```bash
# Check code style
composer cs-check

# Auto-fix code style
composer cs-fix
```

### Generate Documentation

```bash
composer docs
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

- [Ben Kremer](https://benkremer.de)
- [Jochen Wichmann](https://github.com/jochenwichmann)
- [Max Leistner](https://maxleistner.de)

## Links

- [Documentation](https://sixmonkey.github.io/sloth)
- [Issue Tracker](https://github.com/sixmonkey/sloth/issues)
- [Packagist](https://packagist.org/packages/sixmonkey/sloth)
