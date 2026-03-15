# Running Tests

## Prerequisites

- **PHP 8.1+** — Check with `php -v`
- **Composer** — PHP's package manager (like npm for JavaScript)

### Installing Composer

If you don't have Composer installed:

**macOS (Homebrew):**
```bash
brew install composer
```

**Linux:**
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**Windows:**

Download and run the installer from https://getcomposer.org/download/

Verify it's installed:
```bash
composer --version
```

## Setup

From the project root (the directory containing `composer.json`):

```bash
composer install
```

This creates a `vendor/` directory with PHPUnit and its dependencies. You only need to run this once (or again after pulling changes to `composer.json`).

## Running Tests

Run the full test suite:

```bash
./vendor/bin/phpunit
```

Run a specific test file:

```bash
./vendor/bin/phpunit tests/FunctionsTest.php
```

Run a specific test method:

```bash
./vendor/bin/phpunit --filter testSlugify
```

Run with verbose output (shows each test name):

```bash
./vendor/bin/phpunit --testdox
```

## Project Structure

```
composer.json          # Defines PHPUnit dependency
phpunit.xml            # PHPUnit configuration (test directory, bootstrap)
tests/
  bootstrap.php        # Loads autoloader and functions.php before tests run
  FunctionsTest.php    # Tests for functions in functions.php
```

## Writing New Tests

1. Create a new file in `tests/` ending in `Test.php` (e.g., `SearchTest.php`)
2. Extend `PHPUnit\Framework\TestCase`
3. Name test methods starting with `test`

```php
<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SearchTest extends TestCase
{
    public function testSomething(): void
    {
        $this->assertEquals('expected', some_function());
    }
}
```

PHPUnit will automatically discover it — no configuration changes needed.
