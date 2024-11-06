<?php

use Detain\Sshwitch\Sshwitch;
use PHPUnit\Framework\TestCase;
use phpmock\phpunit\PHPMock;

class SshwitchTest extends TestCase
{
    use PHPMock;

    protected function setUp(): void
    {
        // Reset connection and state before each test
        Sshwitch::disconnect();

        // Mock ssh2_connect, ssh2_auth_pubkey_file, ssh2_exec, and other SSH2 calls
        $this->mockSsh2Functions();
    }

    protected function mockSsh2Functions(): void
    {
        $ssh2Connect = $this->getFunctionMock('Detain\Sshwitch', 'ssh2_connect');
        $ssh2Connect->expects($this->any())->willReturn(true);

        $ssh2AuthPubkeyFile = $this->getFunctionMock('Detain\Sshwitch', 'ssh2_auth_pubkey_file');
        $ssh2AuthPubkeyFile->expects($this->any())->willReturn(true);

        $ssh2Exec = $this->getFunctionMock('Detain\Sshwitch', 'ssh2_exec');
        $ssh2Exec->expects($this->any())->willReturn(fopen('php://memory', 'r+'));  // Mock an SSH exec stream

        $streamGetContents = $this->getFunctionMock('Detain\Sshwitch', 'stream_get_contents');
        $streamGetContents->expects($this->any())->willReturn("hostname\ncommand output\nexit");

        $ssh2Disconnect = $this->getFunctionMock('Detain\Sshwitch', 'ssh2_disconnect');
        $ssh2Disconnect->expects($this->any())->willReturn(true);
    }

    public function testConnectAndDisconnect()
    {
        $this->assertFalse(Sshwitch::$connection, 'Should start without an active connection');

        Sshwitch::connect();
        $this->assertNotFalse(Sshwitch::$connection, 'Connection should be established');

        Sshwitch::disconnect();
        $this->assertFalse(Sshwitch::$connection, 'Connection should be closed after disconnecting');
    }

    public function testRunCommands()
    {
        $switch = '10.0.0.1';
        $commands = ['show version', 'show ip interface brief'];
        $output = Sshwitch::run($switch, $commands);

        $this->assertIsArray($output, 'Run should return an array of command outputs');
        $this->assertNotEmpty($output, 'Output should not be empty');
    }

    public function testGetSetProperties()
    {
        Sshwitch::setAutoDisconnect(false);
        $this->assertFalse(Sshwitch::getAutoDisconnect(), 'autoDisconnect should be set to false');

        Sshwitch::setAutoDisconnect(true);
        $this->assertTrue(Sshwitch::getAutoDisconnect(), 'autoDisconnect should be set to true');
    }

    public function testChainingMode()
    {
        Sshwitch::setChaining(true);
        $this->assertTrue(Sshwitch::getChaining(), 'Chaining mode should be enabled');
        
        $result = Sshwitch::setTimeout(5);
        $this->assertSame(Sshwitch::class, $result, 'Method chaining should return the class name');
    }

    public function testInvalidMethodCall()
    {
        $this->expectException(BadMethodCallException::class);
        Sshwitch::nonexistentMethod();
    }
}
