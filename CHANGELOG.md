# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2024-01-01 (Planned)

### Added

- **PHP 8.2 Support**: Minimum PHP version updated to 8.2
- **Modernized Dependencies**: Updated Laravel components to ^10.0
- **PHPStan Integration**: Static analysis with baseline configuration
- **PHP-CS-Fixer**: Code style enforcement with WordPress rules
- **PHPUnit Testing**: Test suite with bootstrap and coverage
- **GitHub Actions CI**: Automated testing and deployment pipeline
- **API Documentation**: PHPDocumentor configuration for API docs
- **Comprehensive Documentation**: README, CONTRIBUTING, LICENSE files

### Changed

- **Core/Application.php**: Modernized with typed properties and return types
- **Core/Sloth.php**: Modernized with typed properties and return types
- **Core/ServiceProvider.php**: Modernized with typed properties and return types
- **Route/Route.php**: Modernized with typed properties, return types, PHPDoc
- **Model/*.php**: Modernized all model classes
- **ServiceProviders**: Updated all service providers with modern PHP
- **Facades**: Updated all facades with modern PHP

### Deprecated

- PHP 8.0 and 8.1 support (minimum now 8.2)

### Removed

### Fixed

### Security

## [1.0.5] - 2023-12-01

### Fixed
- Passing null to deprecated parameters (PHP 8.2 compatibility)

## [1.0.4] - 2023-10-15

### Changed
- Upgraded tracy-gitpanel integration

## [1.0.3] - 2023-08-20

### Changed
- Module system rework for better organization

## [1.0.2] - 2023-05-10

### Fixed
- Removed deprecated Cake packages

## [1.0.1] - 2023-03-15

### Added
- Git panel integration for debugging

## [1.0.0] - 2023-01-01

### Added
- Initial release
- Laravel component integration
- ACF support via Corcel
- Custom routing system
- Module system
- Template hierarchy integration
- Tracy debugging
- Scaffolding tools
