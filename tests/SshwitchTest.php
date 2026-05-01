<?php

namespace Detain\Sshwitch\Tests;

use BadMethodCallException;
use Detain\Sshwitch\Sshwitch;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Detain\Sshwitch\Sshwitch.
 *
 * Every PHP-level SSH function (`ssh2_connect`, `ssh2_auth_pubkey_file`,
 * `ssh2_exec`, `ssh2_disconnect`, `stream_get_contents`) is mocked into the
 * `Detain\Sshwitch` namespace via php-mock-phpunit so the suite never touches
 * a real SSH server.
 *
 * php-mock allows only one active mock per function per test, so each mocked
 * function reads its return value from a class property the individual tests
 * mutate before exercising Sshwitch.
 */
class SshwitchTest extends TestCase
{
    use PHPMock;

    /** @var bool|resource Value the ssh2_connect mock should return. */
    private $mockConnectReturn = true;

    /** @var bool Value the ssh2_auth_pubkey_file mock should return. */
    private bool $mockAuthReturn = true;

    /** @var bool Whether ssh2_exec should hand back a stream (true) or false. */
    private bool $mockExecReturnsStream = true;

    /** @var string Body fed back from the stream_get_contents mock. */
    private string $mockStreamContents = "hostname\ncommand output\nexit";

    /**
     * Reset all static state and (re)install SSH mocks before each test so
     * tests never bleed into one another.
     */
    protected function setUp(): void
    {
        $this->mockConnectReturn = true;
        $this->mockAuthReturn = true;
        $this->mockExecReturnsStream = true;
        $this->mockStreamContents = "hostname\ncommand output\nexit";

        $this->installMocks();
        $this->resetSshwitchState();
    }

    /**
     * Reset Sshwitch's static properties between tests so order can't matter.
     */
    private function resetSshwitchState(): void
    {
        Sshwitch::$connection = false;
        Sshwitch::$timeout = 10;
        Sshwitch::$switch = '';
        Sshwitch::$commands = [];
        Sshwitch::$output = '';
        Sshwitch::$hostname = '';
        Sshwitch::$motd = '';
        Sshwitch::$commandOutputs = [];
        Sshwitch::$autoDisconnect = true;
        Sshwitch::$chaining = false;
    }

    /**
     * Install one mock per SSH function. Each mock reads its return value
     * from a per-test instance property so tests can vary behavior without
     * re-registering the mock (which php-mock forbids).
     */
    private function installMocks(): void
    {
        $this->getFunctionMock('Detain\Sshwitch', 'ssh2_connect')
            ->expects($this->any())
            ->willReturnCallback(fn () => $this->mockConnectReturn);

        $this->getFunctionMock('Detain\Sshwitch', 'ssh2_auth_pubkey_file')
            ->expects($this->any())
            ->willReturnCallback(fn () => $this->mockAuthReturn);

        $this->getFunctionMock('Detain\Sshwitch', 'ssh2_exec')
            ->expects($this->any())
            ->willReturnCallback(function () {
                return $this->mockExecReturnsStream ? fopen('php://memory', 'r+') : false;
            });

        $this->getFunctionMock('Detain\Sshwitch', 'stream_get_contents')
            ->expects($this->any())
            ->willReturnCallback(fn () => $this->mockStreamContents);

        $this->getFunctionMock('Detain\Sshwitch', 'ssh2_disconnect')
            ->expects($this->any())
            ->willReturn(true);
    }

    public function testConnectAndDisconnect(): void
    {
        $this->assertFalse(Sshwitch::$connection, 'Should start without an active connection');

        $result = Sshwitch::connect();
        $this->assertTrue($result, 'connect() should return true on success');
        $this->assertNotFalse(Sshwitch::$connection, 'Connection should be established');

        $disconnect = Sshwitch::disconnect();
        $this->assertTrue($disconnect, 'disconnect() should return true on success');
        $this->assertFalse(Sshwitch::$connection, 'Connection should be cleared after disconnecting');
    }

    public function testConnectIsIdempotent(): void
    {
        Sshwitch::connect();
        $first = Sshwitch::$connection;
        Sshwitch::connect();
        $this->assertSame($first, Sshwitch::$connection, 'connect() should be a no-op when already connected');
    }

    public function testDisconnectIsSafeWhenNotConnected(): void
    {
        // No prior connect() call — disconnect should not error or call ssh2_disconnect on false.
        $this->assertFalse(Sshwitch::$connection);
        $result = Sshwitch::disconnect();
        $this->assertTrue($result, 'disconnect() should return true even when there was nothing to close');
        $this->assertFalse(Sshwitch::$connection);
    }

    public function testConnectFailsWhenSshConnectReturnsFalse(): void
    {
        $this->mockConnectReturn = false;

        $result = Sshwitch::connect();
        $this->assertFalse($result, 'connect() should return false when ssh2_connect fails');
        $this->assertFalse(Sshwitch::$connection);
    }

    public function testConnectFailsWhenAuthFails(): void
    {
        $this->mockAuthReturn = false;

        $result = Sshwitch::connect();
        $this->assertFalse($result, 'connect() should return false when ssh2_auth_pubkey_file fails');
        $this->assertFalse(Sshwitch::$connection, 'connection should be cleared after auth failure');
    }

    public function testRunCommandsArray(): void
    {
        $output = Sshwitch::run('10.0.0.1', ['show version', 'show ip interface brief']);

        // The default mocked stream content does not match the parsing regex —
        // run() should still execute cleanly and return false rather than throwing.
        $this->assertFalse($output);
        $this->assertSame('10.0.0.1', Sshwitch::$switch);
        // 'show hostname' should have been prepended.
        $this->assertSame('show hostname', Sshwitch::$commands[0]);
    }

    public function testRunAcceptsSemicolonSeparatedString(): void
    {
        Sshwitch::run('10.0.0.1', 'show version;show clock');
        $this->assertSame(
            ['show hostname', 'show version', 'show clock'],
            Sshwitch::$commands,
            'String commands should be split on semicolons and prefixed with show hostname'
        );
    }

    public function testRunReturnsFalseWhenSsh2ExecFails(): void
    {
        $this->mockExecReturnsStream = false;

        $output = Sshwitch::run('10.0.0.1', 'show version');
        $this->assertFalse($output, 'run() should return false when ssh2_exec returns false');
    }

    public function testRunReturnsFalseWhenConnectFails(): void
    {
        $this->mockConnectReturn = false;

        $output = Sshwitch::run('10.0.0.1', 'show version');
        $this->assertFalse($output, 'run() should return false when connect() fails');
    }

    public function testRunParsesOutputForKnownGoodResponse(): void
    {
        // Mimic the wire format clogin produces: a prelude/banner, then for each
        // command a `<hostname># <command>` prompt line followed by the response.
        $this->mockStreamContents = "spawn ssh -c aes256-ctr -x sw1.example.com\n"
            . "show hostname\nsw1.example.com\n"
            . "sw1# show hostname\nsw1.example.com\n"
            . "sw1# show version\nCisco IOS XE Software, Version 16.9.4\nuptime is 1 day\n"
            . "sw1# show clock\n12:34:56.789 UTC\n"
            . "sw1# exit\n";

        $return = [];
        $output = Sshwitch::run('sw1.example.com', ['show version', 'show clock'], 'cisco', $return);

        $this->assertIsArray($output, 'run() should return parsed outputs as an array');
        $this->assertSame($return, $output, 'by-reference $return should match the return value');
        $this->assertSame('sw1', Sshwitch::$hostname, 'Hostname should be parsed from show hostname output');
        $this->assertCount(2, $output, 'There should be two parsed command outputs');
        $this->assertSame($output, Sshwitch::$commandOutputs, 'commandOutputs should mirror the return value');
    }

    public function testGetSetProperties(): void
    {
        Sshwitch::setAutoDisconnect(false);
        $this->assertFalse(Sshwitch::getAutoDisconnect(), 'autoDisconnect should be set to false');

        Sshwitch::setAutoDisconnect(true);
        $this->assertTrue(Sshwitch::getAutoDisconnect(), 'autoDisconnect should be set to true');

        Sshwitch::setTimeout(42);
        $this->assertSame(42, Sshwitch::getTimeout(), 'Timeout should round-trip through magic accessors');

        Sshwitch::setSwitch('core-1');
        $this->assertSame('core-1', Sshwitch::getSwitch());
    }

    public function testGetterAlwaysReturnsValueEvenWhenChaining(): void
    {
        Sshwitch::setSwitch('core-1');
        Sshwitch::setChaining(true);
        try {
            $this->assertSame(
                'core-1',
                Sshwitch::getSwitch(),
                'Getters must return the actual value even when chaining is enabled'
            );
        } finally {
            Sshwitch::$chaining = false;
        }
    }

    public function testChainingMode(): void
    {
        Sshwitch::setChaining(true);
        try {
            $this->assertTrue(Sshwitch::getChaining(), 'Chaining mode should be enabled');

            $this->assertSame(Sshwitch::class, Sshwitch::setTimeout(5), 'Setter should return class name when chaining');
            $this->assertSame(Sshwitch::class, Sshwitch::connect(), 'connect() should return class name when chaining');
            $this->assertSame(Sshwitch::class, Sshwitch::disconnect(), 'disconnect() should return class name when chaining');
        } finally {
            Sshwitch::$chaining = false;
        }
    }

    public function testInvalidMethodCall(): void
    {
        $this->expectException(BadMethodCallException::class);
        Sshwitch::nonexistentMethod();
    }

    public function testSetterForUnknownPropertyAlsoThrows(): void
    {
        $this->expectException(BadMethodCallException::class);
        // setNotARealProperty is not a getter/setter for any declared property,
        // so __callStatic should throw rather than silently return false.
        Sshwitch::setNotARealProperty('anything');
    }
}
