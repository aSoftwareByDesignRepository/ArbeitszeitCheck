# Contributing to ArbeitszeitCheck / TimeGuard

Thank you for your interest in contributing to ArbeitszeitCheck! This document provides guidelines and instructions for contributing.

## Code of Conduct

This project adheres to Nextcloud's [Code of Conduct](https://nextcloud.com/code-of-conduct/). By participating, you are expected to uphold this code.

## Getting Started

1. Fork the repository
2. Clone your fork: `git clone https://github.com/YOUR_USERNAME/nextcloud-arbeitszeitcheck.git`
3. Create a new branch: `git checkout -b feature/your-feature-name`
4. Make your changes
5. Test your changes thoroughly
6. Commit your changes: `git commit -m "Add feature: description"`
7. Push to your fork: `git push origin feature/your-feature-name`
8. Create a Pull Request

## Development Setup

### Prerequisites

- PHP 8.1 or higher
- Node.js 16+ and npm 7+
- Composer
- A Nextcloud installation (version 27-29)

### Installation

```bash
cd apps/arbeitszeitcheck
composer install
npm install
npm run build
```

### Running Tests

```bash
# PHP unit tests
vendor/bin/phpunit

# JavaScript tests
npm test
```

## Coding Standards

### PHP

- Follow PSR-12 coding standards
- Use strict types: `declare(strict_types=1);`
- Add type hints and return types to all methods
- Write comprehensive PHPDoc comments
- Follow Nextcloud's app development best practices

### JavaScript/Vue

- Use Vue 3 Composition API or Options API consistently
- Follow Nextcloud Vue component patterns
- Use @nextcloud/vue components only (no custom UI components)
- Follow ESLint rules (run `npm run lint`)
- Use scoped styles with BEM naming: `.timetracking-block__element--modifier`

### CSS

- **CRITICAL**: All CSS must be scoped to prevent conflicts
- Prefix all classes with `timetracking-` or `tt-`
- Use Nextcloud CSS variables (never hardcode colors)
- Use BEM methodology
- Never use global selectors
- Test with other Nextcloud apps active

### Database

- Use Nextcloud's QBMapper pattern (never raw SQL)
- All table names must be short (e.g., `at_entries`, not `arbeitszeitcheck_entries`)
- Include proper indexes
- Write migrations for all schema changes

## Commit Messages

Use clear, descriptive commit messages:

- Use present tense ("Add feature" not "Added feature")
- Reference issue numbers when applicable: "Fix #123: Description"
- Keep the first line under 72 characters
- Add detailed description if needed

Example:
```
Add compliance violation detection

Implements real-time checking for German labor law violations
including break time requirements and maximum working hours.
Includes tests and documentation.
```

## Pull Request Guidelines

1. **Keep PRs focused**: One feature or bug fix per PR
2. **Add tests**: Include unit and/or integration tests
3. **Update documentation**: Update README, CHANGELOG, or other docs as needed
4. **Check accessibility**: Ensure WCAG 2.1 AAA compliance
5. **Test with multiple apps**: Verify no CSS conflicts with other Nextcloud apps
6. **Follow legal requirements**: Ensure GDPR and ArbZG compliance

## Testing Requirements

- Write tests for all new features
- Maintain at least 70% code coverage
- Test accessibility with keyboard navigation and screen readers
- Test CSS isolation with other apps active
- Test on multiple browsers (Chrome, Firefox, Safari, Edge)

## Legal Compliance

**CRITICAL**: This app handles sensitive employee data and must comply with:

- German labor law (Arbeitszeitgesetz - ArbZG)
- GDPR/DSGVO requirements
- Works council rights (Betriebsverfassungsgesetz)

All contributions must maintain legal compliance. When in doubt, consult with legal experts.

## Documentation

- Update user documentation for new features
- Add code comments for complex logic
- Update API documentation for new endpoints
- Keep CHANGELOG.md updated

## Questions?

- Open an issue for bugs or feature requests
- Check existing issues before creating new ones
- Be respectful and professional in all communications

Thank you for contributing to ArbeitszeitCheck!