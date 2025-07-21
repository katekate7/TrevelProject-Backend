# Quick Test Commands

## Most Used Commands

```bash
# Run current file you're viewing
php bin/phpunit tests/Entity/UserTest.php

# Run all unit tests with nice output
php bin/phpunit tests/Entity --testdox

# Run all security tests
php bin/phpunit tests/Security

# Run specific test method
php bin/phpunit tests/Entity/UserTest.php --filter testUserCanBeCreatedWithBasicInfo
```

## File Structure
- `tests/Entity/` - Unit tests ✅
- `tests/Security/` - Security tests ✅  
- `tests/Integration/ItemControllerIntegrationTest.php` - API tests ✅
- `tests/Integration/UserRegistrationIntegrationTest.php` - Registration tests ✅

## Results
When working: `OK (6 tests, 17 assertions)` ✅

## Avoid
Don't run: `php bin/phpunit tests/Integration` (has broken existing tests)
