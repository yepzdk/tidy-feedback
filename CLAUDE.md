# Drupal Coding Standards and Project Conventions

This document outlines the coding standards and conventions to follow for this
Drupal project. Consistent code style improves readability, reduces errors, and
makes collaboration more efficient.

## General Principles

- Follow [Drupal Coding
  Standards](https://www.drupal.org/docs/develop/standards) where possible
- Use modern PHP practices (type hinting, dependency injection, etc.)
- Write clean, readable, and maintainable code
- Document your code thoroughly

## Code Style

### PHP Files

- Use the `.php` extension for PHP files
- Start with `<?php` (no closing PHP tag)
- Include a file-level docblock with a brief description
- Use UTF-8 encoding without BOM
- Use LF (Unix) line endings
- End files with a single blank line

### Naming Conventions

- **Classes**: PascalCase (e.g., `ProfilePictureFixture`)
- **Interfaces**: PascalCase with "Interface" suffix (e.g.,
  `FileRepositoryInterface`)
- **Traits**: PascalCase with "Trait" suffix (e.g., `LoggerTrait`)
- **Methods**: camelCase (e.g., `getUser()`, `loadData()`)
- **Variables**: camelCase (e.g., `$sourceImagePath`, `$fileContents`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `MAX_ITEMS`, `DEFAULT_TIMEOUT`)
- **Functions**: snake_case (for procedural code only, rare in modern Drupal)
- **File names**: Match the contained class name (e.g.,
  `ProfilePictureFixture.php`)

### Indentation and Whitespace

- Use 2 spaces for indentation, not tabs
- Limit line length to approximately 80-100 characters
- Use blank lines to separate logical blocks of code
- Use spaces around operators (`$a = $b + $c;`, not `$a=$b+$c;`)

### Curly Braces and Control Structures

- Opening braces go on the same line for functions, classes, and control
  structures
- Closing braces go on their own line
- Always use braces for control structures (even for single-line statements)

```php
if ($condition) {
  $foo = $bar;
}
else {
  $foo = $baz;
}
```

## Dependency Injection

- Use constructor injection for services
- Avoid using `\Drupal::service()` in classes
- Implement service arguments in the `services.yml` file, or use autowiring
- Use interfaces for type hinting when available

```php
/**
 * Constructs a new MyClass.
 *
 * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
 *   The entity type manager.
 */
public function __construct(
  EntityTypeManagerInterface $entityTypeManager
) {
  $this->entityTypeManager = $entityTypeManager;
}
```

## Path Construction

- Use `__DIR__` for constructing paths relative to the current file
- Avoid using service calls for basic path operations

```php
// Good
$sourceImagePath = __DIR__ . '/../Images/default_profile_picture.jpg';

// Avoid
$modulePath = $moduleExtensionList->getPath('my_module');
$sourceImagePath = $modulePath . '/src/Images/default_profile_picture.jpg';
```

## Logging

- Use logging sparingly and meaningfully
- Log errors and critical information, not routine operations
- Use the appropriate log levels (error, warning, notice, info, debug)
- Prefer throwing exceptions over logging and continuing with invalid state

## PHPDoc Blocks

- Include docblocks for classes, methods, and properties
- Use typed properties in PHP 7.4+ along with docblocks
- Include parameter and return type documentation

```php
/**
 * Creates a new user.
 *
 * @param string $username
 *   The username.
 * @param string $email
 *   The email address.
 *
 * @return \Drupal\user\UserInterface
 *   The created user.
 *
 * @throws \Exception
 *   If the user could not be created.
 */
public function createUser(string $username, string $email): UserInterface {
  // Implementation...
}
```

## Modern PHP Features

- Use PHP 8 features when available:
  - Constructor property promotion for cleaner dependency injection
  - Union types for more flexible type hinting
  - Named arguments for improved readability
  - Match expressions instead of complex switch statements
  - Attributes instead of annotations where appropriate

## Fixtures and Test Data

- Organize fixtures by entity type
- Use dependency injection in fixture classes
- Implement the `DependentFixtureInterface` when fixtures depend on others
- Keep test images and assets in a designated directory (e.g., `src/Images`)
- Use clear reference names for fixture entities

## Error Handling

- Throw specific exceptions with clear error messages
- Validate input parameters thoroughly
- Use proper type hinting to catch errors at compile time when possible
- Document exceptions in method docblocks

## Attribute Overrides

- Use `#[\Override]` for methods that override parent methods or implement
  interface methods
- Do not use `#[\Override]` when the method doesn't actually override anything

## File Organization

- Follow Drupal's recommended directory structure
- Keep fixtures in a dedicated directory
- Organize code by functionality and type
- Use namespaces that reflect the directory structure

## File Attachments

- File attachments are supported for feedback entities
- When displaying file attachments in forms (especially edit forms):
  - Simply provide a link to open the file in a new tab
  - Detect image files using their extension (jpg, jpeg, png, gif)
  - Use `\Drupal::service('file_url_generator')->generateAbsoluteString($file_uri)` 
    to generate proper URLs for files
  - Avoid using deprecated functions like `file_create_url()`
  - Keep the implementation simple - complex image manipulation is unnecessary

```php
// Example of displaying a file attachment link
if (!empty($file_uri)) {
  $url = \Drupal::service('file_url_generator')->generateAbsoluteString($file_uri);
  $filename = basename($file_uri);
  
  $form['file_attachment']['#prefix'] = '<div class="attachment-preview">' .
    '<p>' . $this->t('File attachment: <a href="@url" target="_blank">@filename</a>', 
      ['@url' => $url, '@filename' => $filename]) . '</p>' .
    '</div>';
}
```

## Final Notes

- Write code for readability and maintainability
- Comment "why", not "what" (the code shows what is happening)
- Keep methods short and focused on a single responsibility
- Consider performance implications, especially for fixture loading and data
  processing
