---
name: static-chaining
description: Implements a new static method or property on Sshwitch following the all-static chaining return pattern and __callStatic magic method convention in src/Sshwitch.php. Handles `return self::$chaining ? self::class : $value`. Use when user says 'add method', 'new property', or 'extend the class'. Do NOT use for test code or modifying __callStatic itself.
---
# static-chaining

## Critical

- **Every method** must end with `return self::$chaining ? self::class : $returnValue;` — no exceptions, even void-like methods.
- **Shell args**: wrap every value passed to `ssh2_exec` or shell commands in `escapeshellarg()`.
- **PHP 7.4+** required — `str_starts_with` is used in `__callStatic`; do not polyfill it.
- New properties declared on the class are automatically handled by `__callStatic` getters/setters — do NOT manually write `getX()`/`setX()` unless the property needs custom logic.

## Instructions

1. **Declare the static property** (if the method needs persistent state) at the top of `src/Sshwitch.php`, with the other `public static` properties (lines 7–16). Use the correct type declaration:
   ```php
   public static string $newProp = '';
   // or
   public static array $newProp = [];
   // or
   public static bool $newProp = false;
   ```
   Verify the property appears in the `public static` block before proceeding.

2. **Add the static method** after the last existing method, inside the class, before `__callStatic`. Signature pattern:
   ```php
   public static function myMethod(string $param): mixed
   {
       // ... logic ...
       $result = /* computed value */;
       return self::$chaining ? self::class : $result;
   }
   ```
   - For early-return error paths, use the same chaining pattern:
     ```php
     if (!$condition) {
         return self::$chaining ? self::class : false;
     }
     ```
   - For methods that call `ssh2_exec`, follow the exact stream pattern from `run()`:
     ```php
     $stream = ssh2_exec(self::$connection, $cmd);
     stream_set_blocking($stream, true);
     $output = trim(str_replace("\r", "", stream_get_contents($stream)));
     fclose($stream);
     ```

3. **Shell command construction** — always use `escapeshellarg()` on dynamic values:
   ```php
   $cmd = '/usr/libexec/rancid/clogin -autoenable -t ' . self::$timeout
       . ' -c ' . escapeshellarg(implode(';', self::$commands))
       . ' ' . escapeshellarg(self::$switch);
   ```
   Verify no raw variable is interpolated directly into `$cmd`.

4. **Auto-disconnect** — if the method opens a connection (calls `connect()`), honour `$autoDisconnect` at the end:
   ```php
   if (self::$autoDisconnect) {
       self::disconnect();
   }
   ```

5. **Custom getter/setter needed?** Only add an explicit `getX()`/`setX()` when the property requires validation or side effects. Otherwise the `__callStatic` magic (lines 86–106) handles it for free — no extra code needed.

6. **Run tests** to confirm nothing is broken:
   ```bash
   composer test
   ```

## Examples

**User says:** "Add a `reset()` method that clears commands and output."

**Actions taken:**
- No new property needed.
- Add after `disconnect()`:
  ```php
  public static function reset(): mixed
  {
      self::$commands = [];
      self::$output = '';
      self::$commandOutputs = [];
      self::$hostname = '';
      self::$motd = '';
      return self::$chaining ? self::class : true;
  }
  ```
- Run `composer test` — passes.

**Result:** Callers can chain: `Sshwitch::setChaining(true)::reset()::run($sw, $cmds);`

## Common Issues

- **Chaining broken — method returns `null`:** You forgot the `return` statement entirely or returned `$result` without the ternary. Every code path must have `return self::$chaining ? self::class : $value;`.

- **`BadMethodCallException: Method getFoo does not exist`:** The property `$foo` is not declared `public static` in the class — `__callStatic` calls `property_exists(self::class, $property)` which returns false for undeclared properties. Add the declaration at line ~16.

- **Shell injection / `ssh2_exec` receives unescaped input:** Any dynamic string in `$cmd` that isn't wrapped in `escapeshellarg()` will cause security issues and likely `clogin` parse errors. Check every interpolated variable.

- **`ssh2_connect` / `ssh2_exec` undefined function in tests:** You must mock these via `PHPMock` in namespace `Detain\Sshwitch` (see `tests/SshwitchTest.php`). Never call real SSH in unit tests.

- **State leaking between tests:** `setUp()` must call `Sshwitch::disconnect()` to reset `self::$connection = false` before each test case.