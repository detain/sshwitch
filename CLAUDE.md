# sshwitch

PHP library for running commands on network devices (Cisco, Juniper, etc.) via SSH + RANCID's `clogin`.

## Commands

```bash
composer test                  # phpunit tests
composer install               # install deps
```

## Architecture

- **Main class**: `src/Sshwitch.php` · namespace `Detain\Sshwitch` · all-static pattern
- **Tests**: `tests/SshwitchTest.php` · namespace `Detain\Sshwitch\Tests` · PHPUnit 9 + `php-mock/php-mock`
- **Autoload**: PSR-4 `Detain\Sshwitch\` → `src/` · dev `Detain\Sshwitch\Tests\` → `tests/`

## Key Patterns

**Static properties** in `src/Sshwitch.php`:
- `$connection`, `$timeout`, `$switch`, `$commands`, `$output`, `$hostname`, `$motd`, `$commandOutputs`, `$autoDisconnect`, `$chaining`

**Chaining**: every method returns `self::$chaining ? self::class : $value` — preserve this in all new methods.

**Magic methods**: `__callStatic` handles `get<Prop>()` / `set<Prop>()` for all public static properties.

**`run()` flow**: `connect()` → prepend `show hostname` → build `clogin` cmd via `ssh2_exec()` → parse output with regex → `disconnect()` if `$autoDisconnect`.

**Required constants** (caller must define):
```php
define('CLOGIN_SSH_HOST', '10.0.0.1');
define('CLOGIN_SSH_PORT', 22);
define('CLOGIN_SSH_USER', 'sshuser');
define('CLOGIN_SSH_KEY', '/path/to/private_key');
```

**`clogin` command pattern** in `src/Sshwitch.php`:
```php
$cmd = '/usr/libexec/rancid/clogin -autoenable -t '.self::$timeout.' -c '.escapeshellarg(implode(';', self::$commands)).' '.escapeshellarg($switch);
```

## Testing

Mock all `ssh2_*` and stream functions via `PHPMock` — never call real SSH in tests:
```php
use phpmock\phpunit\PHPMock;
// mock in namespace 'Detain\Sshwitch'
$mock = $this->getFunctionMock('Detain\Sshwitch', 'ssh2_connect');
$mock->expects($this->any())->willReturn(true);
```

`setUp()` must call `Sshwitch::disconnect()` to reset `$connection = false` between tests.

## Conventions

- All methods return `self::$chaining ? self::class : $returnValue` for chaining support
- Use `escapeshellarg()` for all shell arguments passed to `ssh2_exec()`
- Throw `BadMethodCallException` for unknown `__callStatic` calls
- PHP 7.4+ required (`str_starts_with` used in `src/Sshwitch.php`)

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->
