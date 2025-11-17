# Repository Guidelines

## Project Structure & Module Organization
The plugin root contains `Plugin.php` and `config.yml`, which declare EC-CUBE lifecycle hooks and default service parameters. Domain logic lives in `Service/` (diff building and notification delivery) and is consumed by the Doctrine subscriber in `Event/CustomerChangeSubscriber.php`. Mail templates and Symfony service wiring live under `Resource/` (`Resource/config/services.yaml`, `Resource/template/Mail/*.twig`). PHPUnit coverage for service classes sits in `tests/`, mirroring the namespace layout (`tests/Service/DiffBuilderTest.php`). Keep new assets under `Resource/` and follow existing folder names so EC-CUBE can auto-discover them.

## Build, Test, and Development Commands
- `bin/console eccube:plugin:install --code=CustomerChangeNotify`: registers mail templates and service definitions in a running EC-CUBE instance.
- `bin/console eccube:plugin:enable --code=CustomerChangeNotify`: enables the plugin after installation; pair with `bin/console eccube:plugin:disable` when troubleshooting.
- `bin/console cache:clear --no-warmup`: clear caches after changing Twig or service wiring.
- `vendor/bin/phpunit plugins/CustomerChangeNotify/tests`: executes the bundled PHPUnit suite; add `--filter DiffBuilderTest` for focused runs.

## Coding Style & Naming Conventions
Follow PSR-12 formatting (4-space indentation, braces on new lines) and keep properties/methods typed. Event subscribers, services, and DTOs reside under `Plugin\CustomerChangeNotify\{Event,Service}`; new helper classes should respect that namespace to keep auto-wiring simple. Name Twig files under `Resource/template/Mail` using snake_case plus `_mail.twig`. Configuration keys mirror the plugin code, e.g., `customer_change_notify.admin_to`.

## Testing Guidelines
Use PHPUnit for unit coverage. Test classes mirror the source namespace under `tests/` and end with `Test.php`. When adding behavior (e.g., new watched fields in `DiffBuilder`), write regression tests asserting both `Diff::getChanges()` contents and `isEmpty()` edge cases. Aim for repeatable tests without EC-CUBE bootstrapping; stub dependencies like `Eccube\Entity\Customer` as shown in `DiffBuilderTest`.

## Commit & Pull Request Guidelines
Craft imperative commit subjects ("Add diff normalization"), optionally followed by multi-line bodies for rationale. Reference related issues in the body using `refs #123` when applicable. Pull requests should describe behavior changes, highlight configuration impacts (e.g., new parameters in `config.yml`), and include screenshots or log snippets if the user-facing email output changes. Confirm PHPUnit passes and mention any manual verification performed (admin/member email receipts) before requesting review.

## Security & Configuration Tips
Store channel-specific admin addresses via `config.yml > service.customer_change_notify.admin_to`; avoid hardcoding secrets in PHP. When handling request data in `NotificationService`, rely on the injected `RequestStack` and validate headers before logging. Never commit real customer data or SMTP credentials; use `.env.local` in the host EC-CUBE project for secrets and document overrides in the PR.
