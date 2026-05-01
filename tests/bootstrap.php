<?php

/**
 * PHPUnit bootstrap for the Detain\Sshwitch test suite.
 *
 * Defines the `CLOGIN_SSH_*` constants the library expects, then loads the
 * Composer autoloader. Real values are not needed — the SSH layer is mocked
 * in every test via php-mock/php-mock-phpunit.
 */

if (!defined('CLOGIN_SSH_HOST')) {
    define('CLOGIN_SSH_HOST', '127.0.0.1');
}
if (!defined('CLOGIN_SSH_PORT')) {
    define('CLOGIN_SSH_PORT', 22);
}
if (!defined('CLOGIN_SSH_USER')) {
    define('CLOGIN_SSH_USER', 'testuser');
}
if (!defined('CLOGIN_SSH_KEY')) {
    define('CLOGIN_SSH_KEY', '/dev/null');
}

require __DIR__ . '/../vendor/autoload.php';
