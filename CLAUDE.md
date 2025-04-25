# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build Commands
- Development: `cd /Users/jesperpedersen/Projects/personal/tidy_feedback/src && npm install`
- Install in Drupal: Copy module to Drupal's modules directory and enable via UI or Drush

## Coding Standards
- PHP: Follow Drupal coding standards (PSR-2/PSR-12), 4-space indentation, descriptive variable names
- JavaScript: 2-space indentation, use Drupal.behaviors pattern, IIFE with 'use strict'
- CSS: 4-space indentation, descriptive class names, logical property grouping

## Naming Conventions
- PHP: PascalCase for classes, camelCase for methods/variables, UPPER_SNAKE_CASE for constants
- JS: camelCase for variables/functions
- Drupal hooks: module_name_hook_name pattern (e.g., tidy_feedback_form_alter)

## Error Handling
- Use try/catch blocks for PHP exceptions
- Log errors with \Drupal::logger('tidy_feedback')->error()
- Use drupal_set_message() for user-facing errors
- Console.error() for JavaScript debug information

## Module Structure
- Controllers in src/Controller/
- Entities in src/Entity/
- Forms in src/Form/
- JavaScript in js/
- CSS in css/
- Drupal config in config/