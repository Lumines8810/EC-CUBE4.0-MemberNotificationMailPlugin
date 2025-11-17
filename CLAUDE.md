# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a production-ready EC-CUBE 4.0 plugin that sends email notifications when customer information is modified. The plugin monitors customer entity changes via Doctrine events and notifies both administrators and customers. It includes a full admin configuration interface, comprehensive error handling, logging, and extensive test coverage.

## Architecture

### Event-Driven Notification Flow

The plugin uses a Doctrine event subscriber pattern with deferred notification:

1. **CustomerChangeSubscriber** (Event/CustomerChangeSubscriber.php) listens to Doctrine lifecycle events:
   - `onFlush`: Detects Customer entity changes and queues them in `$pendingNotifications`
   - `postFlush`: Sends all queued notifications after transaction commits

2. **DiffBuilder** (Service/DiffBuilder.php) extracts monitored field changes from Doctrine changesets:
   - Watches specific fields configured in services.yaml (name, email, phone, address, etc.)
   - Normalizes values before comparison (trims strings, converts DateTime to ATOM format)
   - Returns a `Diff` object containing only meaningful changes

3. **NotificationService** (Service/NotificationService.php) sends emails:
   - Renders Twig templates for admin and member notifications
   - Uses SwiftMailer to send to both administrator and customer
   - Admin email destination is configurable via `customer_change_notify.admin_to` parameter

### Key Design Patterns

- **Two-phase notification**: Changes are queued in `onFlush` and sent in `postFlush` to ensure DB transaction success before sending emails
- **Strict type comparison**: The DiffBuilder uses strict equality after normalization to detect real changes (e.g., `1` vs `'1'` is considered different)
- **Configurable monitoring**: Watched fields are injected via dependency injection (see services.yaml line 13)

## Testing

### Prerequisites

Before running tests, ensure the following are installed:

- **PHP 7.4+**: Required for PHPUnit execution
- **Composer**: Required to install PHPUnit and test dependencies

### Setup and Execution

```bash
# Install test dependencies (first time only)
composer install

# Run all tests
vendor/bin/phpunit

# Run with coverage report
vendor/bin/phpunit --coverage-html coverage/html

# Run specific test file
vendor/bin/phpunit tests/Service/NotificationServiceTest.php
```

### Test Structure

- Unit tests are located in `tests/Service/` and `tests/Event/`
- Test fixtures (mocks) are in `tests/Fixtures/` - these stub EC-CUBE and third-party dependencies
- `tests/bootstrap.php` provides custom autoloader for fixtures
- Tests are **completely standalone** - no EC-CUBE installation required

### Test Coverage

- `tests/Service/NotificationServiceTest.php` - Mail notification logic
- `tests/Event/CustomerChangeSubscriberTest.php` - Event subscriber behavior
- `tests/Service/DiffBuilderTest.php` - Change detection and normalization

### Key Test Scenarios

- Type strictness: Ensures numeric vs string differences are detected
- String normalization: Verifies whitespace trimming before comparison
- DateTime normalization: Checks equivalent timestamps are treated as unchanged
- Error handling: Validates logging and exception handling

### Troubleshooting Test Execution

**Problem**: `vendor/bin/phpunit: No such file or directory`
- **Cause**: Composer dependencies not installed
- **Solution**: Run `composer install` in the plugin directory

**Problem**: `php: command not found`
- **Cause**: PHP not installed or not in PATH
- **Solution**:
  - macOS: `brew install php`
  - Ubuntu/Debian: `sudo apt-get install php-cli php-xml php-mbstring`
  - Windows: Download from [php.net](https://www.php.net/downloads.php)

**Problem**: `composer: command not found`
- **Cause**: Composer not installed
- **Solution**: Install from [getcomposer.org](https://getcomposer.org/doc/00-intro.md)

**Problem**: Tests fail with class not found errors
- **Cause**: Autoloader not finding fixtures
- **Solution**: Verify `tests/bootstrap.php` is properly configured and all fixture files exist in `tests/Fixtures/`

## Admin Configuration Interface

The plugin provides a web-based configuration interface accessible from the EC-CUBE admin panel:

**Location**: Settings → Customer Change Notification Settings

**Configurable Options**:
- Admin notification email address (defaults to shop email if not set)
- Admin email subject line
- Member email subject line

**Implementation**:
- `Entity/Config.php`: Doctrine entity for storing configuration
- `Repository/ConfigRepository.php`: Repository with `get()` helper method
- `Form/Type/ConfigType.php`: Symfony form with validation
- `Controller/Admin/ConfigController.php`: Admin controller with route `customer_change_notify_admin_config`
- `Resource/template/admin/config.twig`: Admin UI template
- `Nav.php`: Registers menu item in admin navigation

Configuration is stored in the `plg_customer_change_notify_config` table and automatically created/destroyed during install/uninstall.

## Error Handling and Logging

The plugin includes comprehensive error handling and logging throughout:

**NotificationService** (Service/NotificationService.php):
- Logs all customer change detections at INFO level
- Logs mail sending start/success at INFO level
- Logs mail sending failures at WARNING level
- Logs exceptions at ERROR level with full stack traces
- Wraps all operations in try-catch to prevent transaction rollbacks

**CustomerChangeSubscriber** (Event/CustomerChangeSubscriber.php):
- Logs entity update detection at DEBUG level
- Logs when monitored fields have no changes at DEBUG level
- Logs queue additions at INFO level
- Logs postFlush processing start/completion at INFO level
- Logs errors during notification processing at ERROR level

All log entries are prefixed with `[CustomerChangeNotify]` for easy filtering.

## Plugin Lifecycle Management

### PluginManager.php

The plugin uses EC-CUBE 4.0 standard lifecycle management via `PluginManager.php`:

**Key Points**:
- Extends `AbstractPluginManager` (EC-CUBE 4.0 standard)
- Uses Symfony DI `ContainerInterface` for service access
- Method signature: `(array $meta, ContainerInterface $container)`
- Automatically creates/destroys database tables during install/uninstall

**Lifecycle Methods**:
- `install()`: Creates Config table, registers MailTemplates
- `uninstall()`: Removes MailTemplates, drops Config table
- `enable()`: Plugin activation (currently empty)
- `disable()`: Plugin deactivation (currently empty)
- `update()`: Plugin updates (currently empty)

**Important**: This follows EC-CUBE 4.0 naming conventions. EC-CUBE 3.x used `Plugin.php` extending `AbstractPlugin` with different method signatures.

## Development Commands

### Plugin Installation/Management

EC-CUBE plugins are typically managed via the EC-CUBE admin panel or bin/console:

```bash
# Install plugin (creates MailTemplate records and Config table)
bin/console eccube:plugin:install --code=CustomerChangeNotify

# Enable plugin
bin/console eccube:plugin:enable --code=CustomerChangeNotify

# Disable plugin
bin/console eccube:plugin:disable --code=CustomerChangeNotify

# Uninstall plugin (removes MailTemplate records)
bin/console eccube:plugin:uninstall --code=CustomerChangeNotify
```

### Configuration

Plugin configuration is in `config.yml`:
- Plugin name, code, version, and description
- `customer_change_notify.admin_to`: Override admin notification email (null = use BaseInfo email)

Dependency injection is configured in `Resource/config/services.yaml`:
- Monitored fields list is injected into DiffBuilder
- Services are registered with Symfony DI container

### Email Templates

Email templates are located in `Resource/template/Mail/`:
- `customer_change_admin_mail.twig`: Admin notification template
- `customer_change_member_mail.twig`: Customer notification template

Template variables available:
- `Customer`: The Customer entity object
- `diff`: Array of changes with structure `{field, label, old, new, old_formatted, new_formatted}`
- `request`: HttpFoundation Request object (may be null)

## Important Implementation Notes

### Adding New Monitored Fields

To monitor additional Customer fields:

1. Add field name to the array in `Resource/config/services.yaml:13`
2. Add corresponding label to `DiffBuilder::$fieldLabels` in `Service/DiffBuilder.php:61-74`
3. If the field has custom formatting needs, update `DiffBuilder::formatValue()` or `DiffBuilder::normalize()`

### Mail Template Registration

- Mail templates are registered as `MailTemplate` entities during plugin installation (Plugin.php:32-33)
- Template file names must match: `CustomerChangeNotify/admin` and `CustomerChangeNotify/member`
- Templates are removed on plugin uninstall (Plugin.php:50-56)

### Normalization Rules

The DiffBuilder normalizes values before comparison:
- Strings: Trimmed of leading/trailing whitespace
- DateTime: Converted to ATOM format for consistent comparison
- Other types: Used as-is for strict equality check

This ensures that `'Alice'` and `' Alice  '` are treated as the same value, preventing false-positive notifications.
