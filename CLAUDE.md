* /opt/homebrew/opt/php@8.4/bin/php
* Prefer Mockery in unit tests.
* Always run `phpcbf` + `phpcs` + `phpstan` on new and edited code
* UI changes should have Playwright tests
* Do not auto-commit unless explicitly requested by the user
* Run `composer dump-autoload` after creating new classes or changing namespaces
* Use `declare(strict_types=1);` in all PHP files
* Don't add PhpDoc return type when it is the same as the PHP function signature return type
* Playwright E2E tests should use REST and WP CLI to arrange the test and only use UI for the minimal part being tested. The assertion should preferably be via REST but UI is reasonable if the page loaded shows the result. Custom REST endpoints for arranging tests can be added in development-plugin/rest. Do not use REST endpoints when a setting does not need to be changed during a test, instead use tests/_wp-env/initialize-internal.sh. 
* PRs should contain screenshots of changes
* methods that are hooked to WordPress actions and filters should have PhpDoc `@hooked` annotation with the name and `@see` annotation linking to the call site
* Prefer Mockery in unit tests.
* API methods should return simple objects and not arrays unless the array is a simple list of items
* 
