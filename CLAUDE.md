# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build/Lint/Test Commands
- Install module: Standard Drupal module installation
- Run all tests: `./vendor/bin/phpunit modules/custom/tidy_feedback`
- Run single test: `./vendor/bin/phpunit modules/custom/tidy_feedback/tests/src/SomeTest.php`
- Lint PHP: `phpcs --standard=Drupal modules/custom/tidy_feedback`

## Code Style Guidelines
- PHP: Drupal coding standards (PSR-2/PSR-4)
  - Classes: PascalCase with namespaces Drupal\tidy_feedback\...
  - Methods: camelCase for class methods, snake_case for hooks
  - Indentation: 4 spaces
  - Documentation: Full docblocks for all methods
- JavaScript: Drupal JS standards
  - Use IIFE with "use strict"
  - Drupal.behaviors pattern for attaching JS
  - Indentation: 2 spaces
- CSS: BEM-like naming (tidy-feedback-component)
- Error handling: try-catch with Drupal logging (\Drupal::logger())
- All files should have appropriate file-level documentation