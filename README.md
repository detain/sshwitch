# sshwitch SSH Switch
Login and run commands on your network devices via an ssh connection on a remote server. Easy to use PHP static class methods with static chaining support for rapid development.  Supports Cisco and Juniper network routers and switches with more easily added.

We handle the switch login and elevation using [rancid](https://www.shrubbery.net/rancid/) and it supports more than what I've adde so far. It would take only a few lines of code to add support to login to devices from Extreme Networks, Juniper, Procket Networks, Redback, A10, Alteon, Avocent (Cyclades), Bay Networks (Nortel), ADC-kentrox, Foundry, HP, Hitachi, Juniper Networks, MRV, Mikrotik, Netscreen, Nokia (Alcatel-Lucent), Netscaler, Riverstone, Netopia, Xirrus, and Arrcus.

## Requirements
- PHP 7.4 or later
- `ssh2` PHP extension
- RANCID's `clogin` script for certain network commands (e.g., Cisco devices)

## Installation
You can install SSHwitch via Composer:
```bash
composer require detain/sshwitch
```

## Usage

### Connecting and Executing Commands

```php
use Detain\Sshwitch\Sshwitch;

// Set up SSHwitch with a switch and commands
$switch = '10.0.0.1'; // IP address or hostname of the switch
$commands = [
    'show version',
    'show ip interface brief'
];

// Run commands on the switch
$result = Sshwitch::run($switch, $commands);

// Check the output
if ($result !== false) {
    echo "Commands executed successfully. Output:\n";
    print_r($result);
} else {
    echo "Failed to execute commands.";
}
```

### Getting and Setting Properties
SSHwitch uses magic methods for getting and setting static properties.

```php
// Set autoDisconnect to false
Sshwitch::setAutoDisconnect(false);

// Check if chaining mode is enabled
$chaining = Sshwitch::getChaining();
echo "Chaining mode is " . ($chaining ? 'enabled' : 'disabled');
```

## Configuration

The following constants are required for SSHwitch to connect to the switch:

- `CLOGIN_SSH_HOST` - The SSH host address of the device.
- `CLOGIN_SSH_PORT` - The SSH port to use for the connection.
- `CLOGIN_SSH_USER` - The username for SSH login.
- `CLOGIN_SSH_KEY` - The file path to the SSH private key.

### Example Config
```php
define('CLOGIN_SSH_HOST', '10.0.0.1');
define('CLOGIN_SSH_PORT', 22);
define('CLOGIN_SSH_USER', 'sshuser');
define('CLOGIN_SSH_KEY', '/path/to/private_key');
```

## Methods

### `connect()`
Connects to the SSH server if not already connected.

### `disconnect()`
Disconnects the current SSH connection.

### `run($switch, $commands, $type = 'cisco')`
Runs commands on the specified switch and returns the output.

### Magic Methods
- `get<PropertyName>()` and `set<PropertyName>()` for getting and setting public static properties.

## License
This project is licensed under the GNU GPL v3 license.
