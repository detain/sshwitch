---
name: phpunit-ssh-mock
description: Writes PHPUnit tests using PHPMock to mock ssh2_connect, ssh2_auth_pubkey_file, ssh2_exec, stream_get_contents, and ssh2_disconnect in namespace Detain\Sshwitch. Mirrors the pattern in tests/SshwitchTest.php. Use when user says 'add test', 'write test for', or 'test this method'. Do NOT use when testing non-SSH logic or pure property/math logic that has no SSH calls.
---
# phpunit-ssh-mock

## Critical

- **Always mock in namespace `Detain\Sshwitch`** — PHPMock overrides functions per-namespace. Mocking in the wrong namespace silently fails and calls real SSH.
- **Never call real SSH in tests** — no live `ssh2_*` calls, no real host connections.
- **`setUp()` MUST call `Sshwitch::disconnect()`** first to reset `$connection = false` before each test.
- Required constants must be defined before any test that calls `connect()` or `run()`:
  ```php
  define('CLOGIN_SSH_HOST', '10.0.0.1');
  define('CLOGIN_SSH_PORT', 22);
  define('CLOGIN_SSH_USER', 'sshuser');
  define('CLOGIN_SSH_KEY', '/path/to/private_key');
  ```
- Test class does NOT use `namespace` — it lives at global scope in `tests/SshwitchTest.php`.

## Instructions

1. **Add imports** at the top of `tests/SshwitchTest.php` (no namespace declaration):
   ```php
   use Detain\Sshwitch\Sshwitch;
   use PHPUnit\Framework\TestCase;
   use phpmock\phpunit\PHPMock;
   ```
   Verify `phpmock/phpunit` is in `composer.json` under `require-dev` before proceeding.

2. **Declare the test class** extending `TestCase` with the `PHPMock` trait:
   ```php
   class SshwitchTest extends TestCase
   {
       use PHPMock;
   ```

3. **Implement `setUp()`** — reset static state then install all SSH mocks:
   ```php
   protected function setUp(): void
   {
       Sshwitch::disconnect();
       $this->mockSsh2Functions();
   }
   ```

4. **Implement `mockSsh2Functions()`** — mock every `ssh2_*` and stream function used by `src/Sshwitch.php`, all in namespace `'Detain\Sshwitch'`:
   ```php
   protected function mockSsh2Functions(): void
   {
       $ssh2Connect = $this->getFunctionMock('Detain\Sshwitch', 'ssh2_connect');
       $ssh2Connect->expects($this->any())->willReturn(true);

       $ssh2AuthPubkeyFile = $this->getFunctionMock('Detain\Sshwitch', 'ssh2_auth_pubkey_file');
       $ssh2AuthPubkeyFile->expects($this->any())->willReturn(true);

       $ssh2Exec = $this->getFunctionMock('Detain\Sshwitch', 'ssh2_exec');
       $ssh2Exec->expects($this->any())->willReturn(fopen('php://memory', 'r+'));

       $streamGetContents = $this->getFunctionMock('Detain\Sshwitch', 'stream_get_contents');
       $streamGetContents->expects($this->any())->willReturn("hostname\ncommand output\nexit");

       $ssh2Disconnect = $this->getFunctionMock('Detain\Sshwitch', 'ssh2_disconnect');
       $ssh2Disconnect->expects($this->any())->willReturn(true);
   }
   ```
   Verify each function name exactly matches what `src/Sshwitch.php` calls before proceeding.

5. **Write test methods** using `Sshwitch::` static calls. Pattern for each test:
   - Assert pre-conditions on static properties (e.g. `$this->assertFalse(Sshwitch::$connection)`)
   - Call the method under test
   - Assert the return value or property change

6. **Run tests** to confirm all pass:
   ```bash
   composer test
   ```

## Examples

**User says:** "Add a test for `connect()` and `disconnect()`"

**Actions taken:**
- `setUp()` calls `Sshwitch::disconnect()` then `mockSsh2Functions()`
- Test asserts `$connection` is false before connect, not-false after, false again after disconnect

**Result:**
```php
public function testConnectAndDisconnect()
{
    $this->assertFalse(Sshwitch::$connection, 'Should start without an active connection');
    Sshwitch::connect();
    $this->assertNotFalse(Sshwitch::$connection, 'Connection should be established');
    Sshwitch::disconnect();
    $this->assertFalse(Sshwitch::$connection, 'Connection should be closed after disconnecting');
}
```

**User says:** "Write a test for `run()`"

```php
public function testRunCommands()
{
    $output = Sshwitch::run('10.0.0.1', ['show version', 'show ip interface brief']);
    $this->assertIsArray($output, 'Run should return an array of command outputs');
    $this->assertNotEmpty($output, 'Output should not be empty');
}
```

**User says:** "Test the chaining mode"

```php
public function testChainingMode()
{
    Sshwitch::setChaining(true);
    $this->assertTrue(Sshwitch::getChaining(), 'Chaining mode should be enabled');
    $result = Sshwitch::setTimeout(5);
    $this->assertSame(Sshwitch::class, $result, 'Method chaining should return the class name');
}
```

## Common Issues

- **"Call to undefined function ssh2_connect"**: The `php-ssh2` extension is not installed. Install it (`apt install php-ssh2`) or ensure it is listed in `composer.json` suggest-ext. Mocks bypass the extension at runtime but it must be loadable.
- **Mock not intercepting calls / real SSH attempted**: You mocked in the wrong namespace. Must be `'Detain\Sshwitch'` — not `''` or `'global'`. Double-check the string passed to `getFunctionMock()`.
- **"Cannot redeclare function" or mock conflict between tests**: PHPMock mocks are per-test. If `mockSsh2Functions()` is called outside `setUp()` (e.g., in a `@dataProvider`), mocks may collide. Keep all mock setup inside `setUp()`.
- **`$connection` not reset between tests causing false-positive**: `setUp()` must call `Sshwitch::disconnect()` *before* `mockSsh2Functions()`. If called after, the disconnect mock fires instead of the real reset.
- **`stream_get_contents` returns empty string**: The mock's `willReturn` value must contain output matching the regex in `run()`. Use `"hostname\ncommand output\nexit"` as the baseline fake output.