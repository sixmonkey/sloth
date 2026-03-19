# Contributing to Sloth

Thank you for your interest in contributing to Sloth! This document provides guidelines and instructions for contributing.

## Code of Conduct

By participating in this project, you agree to abide by our code of conduct. Please be respectful and constructive in all interactions.

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer 2.0 or higher
- WordPress 5.0 or higher
- A local development environment

### Setup

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/YOUR_USERNAME/sloth.git
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
4. Create a feature branch:
   ```bash
   git checkout -b feature/your-feature-name
   ```

## Development Workflow

### 1. Coding Standards

We use PHP-CS-Fixer with WordPress coding standards. Before committing:

```bash
# Check code style
composer cs-check

# Auto-fix code style
composer cs-fix
```

### 2. Static Analysis

We use PHPStan for static analysis. Run before committing:

```bash
composer analyse
```

### 3. Testing

All new features should include tests. Run the test suite:

```bash
composer test
```

For test coverage:

```bash
composer test -- --coverage
```

### 4. Documentation

Update documentation for any changes:

- Update `README.md` if adding new features
- Add PHPDoc comments to all classes and methods
- Update `CHANGELOG.md` with your changes

Generate API documentation:

```bash
composer docs
```

## Pull Request Process

### 1. Before Submitting

- Ensure all tests pass
- Run static analysis with no new errors
- Fix any code style issues
- Update documentation
- Add tests for new functionality

### 2. PR Description

Include in your PR description:

- **Summary**: What does this PR do?
- **Issue**: Link to related issue (if applicable)
- **Changes**: Detailed list of changes
- **Testing**: How to test the changes
- **Screenshots**: For UI changes

### 3. PR Checklist

- [ ] Code follows our style guidelines
- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] No new PHPStan errors
- [ ] Commit messages are clear

## Commit Messages

Follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
type(scope): description

[optional body]

[optional footer]
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation
- `style`: Code style (no logic change)
- `refactor`: Code refactoring
- `test`: Adding tests
- `chore`: Maintenance tasks

### Examples

```
feat(routing): add support for route groups
fix(model): resolve issue with Post::find method
docs(readme): add installation instructions
test(validation): add tests for custom validators
```

## Branch Naming

- `feature/your-feature` - New features
- `fix/issue-description` - Bug fixes
- `docs/description` - Documentation
- `refactor/description` - Code refactoring
- `hotfix/issue` - Urgent fixes

## Reporting Issues

### Bug Reports

Include:
- PHP version
- WordPress version
- Steps to reproduce
- Expected vs actual behavior
- Error messages/logs

### Feature Requests

Include:
- Use case description
- Proposed solution
- Alternative solutions considered

## Questions?

- Open an issue for bugs/features
- Check existing issues before creating new ones
- Be patient - we try to respond within 48 hours

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
