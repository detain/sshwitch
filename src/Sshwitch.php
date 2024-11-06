<?php

namespace Detain\Sshwitch;

class Sshwitch
{
    public static $connection = false; 
    public static int $timeout = 10;
    public static string $switch = '';
    public static array $commands = [];
    public static string $output = '';
    public static string $hostname = '';
    public static string $motd = '';
    public static array $commandOutputs = [];
    public static bool $autoDisconnect = true;
    public static bool $chaining = false;
    
    public static function connect() {
        if (!self::$connection) {
            self::$connection = ssh2_connect(CLOGIN_SSH_HOST, CLOGIN_SSH_PORT, ['hostkey' => 'ssh-rsa']);
            if (!self::$connection) {
                myadmin_log('myadmin', 'debug', 'mars ssh returned connection:' . var_export(self::$connection, true), __LINE__, __FILE__);
                return self::$chaining ? self::class : false;
            }
            if (!ssh2_auth_pubkey_file(self::$connection, CLOGIN_SSH_USER, CLOGIN_SSH_KEY.'.pub', CLOGIN_SSH_KEY)) {
                myadmin_log('myadmin', 'debug', 'mars ssh pubkey authentication failed:' . var_export(self::$connection, true), __LINE__, __FILE__);
                self::disconnect();
                return self::$chaining ? self::class : false;
            }
        }
    }
    
    public static function disconnect() {
        $return = ssh2_disconnect(self::$connection);
        self::$connection = false;
        return self::$chaining ? self::class : $return;
    }
    
    public static function run(string $switch, $commands, $type = 'cisco', &$return = [])
    {
        self::$switch = $switch;
        if (!is_array($commands)) {
            $commands = explode(';', $commands);
        }
        self::$commands = $commands;
        self::connect();
        array_unshift(self::$commands, 'show hostname');
        $cmd = '/usr/libexec/rancid/clogin -autoenable -t '.self::$timeout.' -c '.escapeshellarg(implode(';', self::$commands)).' '.escapeshellarg($switch);
        //myadmin_log('myadmin', 'debug', 'switch cmds:' .$cmd, __LINE__, __FILE__);
        $stream = ssh2_exec(self::$connection, $cmd);
        stream_set_blocking($stream, true);
        self::$output = trim(str_replace("\r", "", stream_get_contents($stream)));
        //myadmin_log('myadmin', 'debug', 'switch cmds op:' .var_export(self::$output, true), __LINE__, __FILE__);
        fclose($stream);
        if (self::$autoDisconnect) {
            self::disconnect();
        }

        if (preg_match_all('/show hostname$\n(\S+)\s*$/msuU', self::$output, $matches)) {
            //self::$hostname = $matches[1][0];
            self::$hostname = explode('.', $matches[1][0])[0];
            $regex = '/^(.*)';
            foreach (self::$commands as $command) {
                //myadmin_log('myadmin', 'debug', $command, __LINE__, __FILE__);
                //$regex .= '\n^'.self::$hostname.'[^\n]* '.preg_quote($command, '/').'$\n(.*)';
                $regex .= '^'.self::$hostname.'[^\n]* '.preg_quote($command, '/').'$\n(.*)';
            }
            //$regex .= '\n^'.self::$hostname.'[^\n]*exit$/msuU';
            $regex .= '^'.self::$hostname.'[^\n]*exit$/msuU';
            //myadmin_log('myadmin', 'debug', 'regx' .var_export($regex, true), __LINE__, __FILE__);
            if (preg_match_all($regex, self::$output, $matches)) {
                $return = [];
                for ($idx = 3, $idxMax = count($matches); $idx < $idxMax; $idx++) {
                    $return[] = $matches[$idx][0];
                }
                return self::$chaining ? self::class : $return;
            }
        }
        return self::$chaining ? self::class : false;
    }
    
    public static function __callStatic($name, $arguments) {
        $property = lcfirst(substr($name, 3));  // Extracts the property name

        // Check if the method is a getter (starts with 'get')
        if (str_starts_with($name, 'get')) {
            if (property_exists(self::class, $property)) {
                return self::${$property};
            }
        }

        // Check if the method is a setter (starts with 'set')
        if (str_starts_with($name, 'set')) {
            if (property_exists(self::class, $property)) {
                self::${$property} = $arguments[0];
                return self::$chaining ? self::class : true;
            }
            return self::$chaining ? self::class : false;
        }

        throw new BadMethodCallException("Method $name does not exist");
    }    
}
