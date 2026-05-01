# sshwitch — SSH Switch / Router Helper

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version](https://img.shields.io/badge/php-%5E7.4%20%7C%7C%20%5E8.0-8892BF.svg)](https://www.php.net/)

`sshwitch` is a small PHP library that runs commands on network devices
(switches, routers, firewalls) by connecting from a relay box to the device
through [RANCID's `clogin`](https://www.shrubbery.net/rancid/) script.

It's built around an all-static, optionally-chainable API that's pleasant to
embed in operations scripts — pass a switch IP and a list of commands, get
back the parsed per-command output.

`clogin` itself supports a long list of vendors: Cisco, Juniper, Extreme
Networks, Procket Networks, Redback, A10, Alteon, Avocent (Cyclades), Bay
Networks (Nortel), ADC-kentrox, Foundry, HP, Hitachi, MRV, Mikrotik,
Netscreen, Nokia (Alcatel-Lucent), Netscaler, Riverstone, Netopia, Xirrus,
Arrcus, and more. This library has been used most heavily against Cisco IOS
and Juniper but the transport is identical for any device `clogin` knows.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [How it works](#how-it-works)
- [API Reference](#api-reference)
  - [Methods](#methods)
  - [Properties (via magic accessors)](#properties-via-magic-accessors)
  - [Static chaining](#static-chaining)
- [Examples](#examples)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [License](#license)

## Requirements

- **PHP 7.4 or later** (PHP 8.x supported and recommended)
- **`ssh2` PHP extension** — usually packaged as `php-ssh2` / `php8.x-ssh2`
- A **relay/jump host** reachable over SSH that has RANCID's `clogin` script
  installed at `/usr/libexec/rancid/clogin` (the path is hard-coded in the
  current version)
- An **SSH keypair** trusted by the relay; the public key file lives at
  `<key>.pub` next to the private key

## Installation

Install via Composer:

```bash
composer require detain/sshwitch
```

Then ensure the `ssh2` extension is loaded:

```bash
php -m | grep ssh2
```

If it's missing, install it (Debian/Ubuntu: `apt install php-ssh2`).

## Quick Start

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Detain\Sshwitch\Sshwitch;

// 1. Tell sshwitch how to reach your relay box
define('CLOGIN_SSH_HOST', '10.0.0.1');
define('CLOGIN_SSH_PORT', 22);
define('CLOGIN_SSH_USER', 'sshuser');
define('CLOGIN_SSH_KEY',  '/home/sshuser/.ssh/id_rsa');

// 2. Run commands against a target switch through that relay
$result = Sshwitch::run('switch01.example.net', [
    'show version',
    'show ip interface brief',
]);

if ($result === false) {
    fwrite(STDERR, "Failed to talk to the switch.\n");
    exit(1);
}

foreach ($result as $i => $output) {
    echo "=== Command #{$i} ===\n{$output}\n";
}
```

## Configuration

The library reads four `define()`'d constants. They must be defined **before**
calling `connect()` or `run()`:

| Constant            | Description                                     | Example                          |
|---------------------|-------------------------------------------------|----------------------------------|
| `CLOGIN_SSH_HOST`   | IP/hostname of the relay box                    | `'10.0.0.1'`                     |
| `CLOGIN_SSH_PORT`   | SSH port on that relay                          | `22`                             |
| `CLOGIN_SSH_USER`   | SSH username on the relay                       | `'sshuser'`                      |
| `CLOGIN_SSH_KEY`    | Path to private key (public key sits at `.pub`) | `'/home/sshuser/.ssh/id_rsa'`    |

The relay box is also where `/usr/libexec/rancid/clogin` must exist.
RANCID's `.cloginrc` on that box is what controls device-side credentials.

## How it works

```
   ┌────────────┐   ssh2 (php-ssh2)   ┌─────────────┐    clogin    ┌────────────┐
   │ your PHP   │ ──────────────────► │  relay box  │ ───────────► │  switch /  │
   │ script     │ ◄────────────────── │ (CLOGIN_*)  │ ◄─────────── │   router   │
   └────────────┘                     └─────────────┘              └────────────┘
```

1. `connect()` opens an SSH session from your PHP process to the relay box
   using the `CLOGIN_SSH_*` constants.
2. `run($switch, $commands)` builds a `clogin` command line, prepends
   `show hostname` so the parser can anchor on the device prompt, and runs
   it through `ssh2_exec()`.
3. The combined stream is parsed: the prelude/banner is dropped, each
   command's output is captured into its own array entry, and noisy IOS
   progress bars (`[##### ] N%`) are collapsed to their final entry to keep
   logs sane.
4. When `$autoDisconnect` is true (default), the SSH session is closed.

## API Reference

### Methods

| Method                                                              | Description                                                                                       |
|---------------------------------------------------------------------|---------------------------------------------------------------------------------------------------|
| `Sshwitch::connect()`                                               | Opens (and authenticates) the SSH session if not already open. Returns `true`/`false`.            |
| `Sshwitch::disconnect()`                                            | Closes the SSH session if one is open. Safe to call when nothing is connected.                    |
| `Sshwitch::run(string $switch, $commands, string $type='cisco', array &$return=[])` | Runs `$commands` against `$switch`, returns the parsed output array (or `false` on failure).      |
| `Sshwitch::__callStatic($name, $args)`                              | Magic dispatch for the auto-generated `getXxx()`/`setXxx()` accessors.                             |

`run()`'s `$type` parameter is currently unused — it's reserved for future
overloads that branch on device family without a signature change. Leave the
default unless you're a contributor wiring a vendor-specific code path.

`run()`'s `$return` is an out-parameter that mirrors the return value;
either is fine, depending on which style you prefer.

### Properties (via magic accessors)

Every public static property has an auto-generated `get`/`set` pair. Setters
return `true` on success (or the class name in chaining mode); getters
always return the value:

| Property          | Type            | Default | Get / Set                                       |
|-------------------|-----------------|---------|--------------------------------------------------|
| `$connection`     | `resource\|false` | `false` | `getConnection()`, `setConnection($r)`           |
| `$timeout`        | `int`           | `10`    | `getTimeout()`, `setTimeout(int)` (clogin `-t`)  |
| `$switch`         | `string`        | `''`    | `getSwitch()`, `setSwitch(string)`               |
| `$commands`       | `array`         | `[]`    | `getCommands()`, `setCommands(array)`            |
| `$output`         | `string`        | `''`    | `getOutput()`, `setOutput(string)`               |
| `$hostname`       | `string`        | `''`    | `getHostname()`, `setHostname(string)`           |
| `$motd`           | `string`        | `''`    | `getMotd()`, `setMotd(string)`                   |
| `$commandOutputs` | `array`         | `[]`    | `getCommandOutputs()`, `setCommandOutputs(array)` |
| `$autoDisconnect` | `bool`          | `true`  | `getAutoDisconnect()`, `setAutoDisconnect(bool)` |
| `$chaining`       | `bool`          | `false` | `getChaining()`, `setChaining(bool)`             |

Calling a `setXxx()` for a property that doesn't exist throws
`BadMethodCallException` (so does any other unknown method).

### Static chaining

Set `Sshwitch::$chaining = true` (or `Sshwitch::setChaining(true)`) to make
**setters** and **action methods** (`connect`, `disconnect`, `run`) return
the class name string instead of their natural return value, allowing PHP's
`::` chaining syntax:

```php
Sshwitch::setChaining(true)
    ::setTimeout(15)
    ::setAutoDisconnect(false)
    ::run('switch01.example.net', 'show version');

// Disable chaining when you actually want a value back:
$output = Sshwitch::setChaining(false)::getOutput();
```

Getters never chain — calling `getOutput()` always returns the value, even
when chaining is on, because chained getters would be useless.

## Examples

### Run a single command

```php
$out = Sshwitch::run('sw01.example.net', 'show clock');
echo $out[0] ?? 'no output';
```

### Pass commands as a semicolon-separated string

```php
Sshwitch::run('sw01.example.net', 'show version;show clock;show ip route summary');
```

### Reuse one SSH session for several `run()` calls

```php
Sshwitch::setAutoDisconnect(false);
Sshwitch::run('sw01.example.net', 'show version');
Sshwitch::run('sw02.example.net', 'show version');
Sshwitch::disconnect();
```

### Inspect raw vs parsed output

```php
$parsed = Sshwitch::run('sw01.example.net', 'show inventory');

echo "Raw transcript:\n",   Sshwitch::getOutput(),   "\n\n";
echo "Parsed entries:\n",   print_r($parsed, true);
echo "Device hostname:\n",  Sshwitch::getHostname(), "\n";
```

### Handle failures gracefully

`connect()` returns `false` on connect/auth failure; `run()` returns `false`
when `connect()` fails, when `ssh2_exec` itself returns false, or when the
output can't be parsed:

```php
$result = Sshwitch::run('sw01.example.net', 'show version');
if ($result === false) {
    error_log('switch run failed; raw output: ' . Sshwitch::getOutput());
}
```

## Testing

The test suite mocks every `ssh2_*` and stream function in the
`Detain\Sshwitch` namespace with
[`php-mock/php-mock-phpunit`](https://github.com/php-mock/php-mock-phpunit),
so no real SSH server is ever contacted.

```bash
composer install
composer test                # run the suite
composer test:coverage       # text coverage report (needs xdebug/pcov)
```

The PHPUnit configuration lives in `phpunit.xml.dist` and bootstraps
`tests/bootstrap.php`, which defines stub `CLOGIN_SSH_*` constants for the
test run.

## Troubleshooting

**`Undefined constant CLOGIN_SSH_HOST`** — define the four constants listed
under [Configuration](#configuration) before calling any Sshwitch method.

**`ssh2_connect`: Could not connect** — verify you can SSH from the host
running PHP to the relay box manually using the same private key and user.

**`run()` returns `false` but `getOutput()` shows data** — the parser
anchors on `<hostname># <command>` prompt lines. If the device prompt isn't
present (e.g. cmdline ran on a non-clogin shell), parsing won't match.

**`Trait "phpmock\phpunit\PHPMock" not found`** — run
`composer install --dev` so `php-mock/php-mock-phpunit` is available.

**Idle, hung connections from earlier scripts** — call `Sshwitch::disconnect()`
explicitly when `$autoDisconnect` is off, otherwise PHP will eventually
close the resource on shutdown.

## License

GNU General Public License v3.0 — see [LICENSE](LICENSE).
