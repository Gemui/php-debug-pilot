# PHP Debug Pilot 🚀

**Zero-Configuration PHP Debugging Setup.**

PHP Debug Pilot is a CLI tool that automatically detects your environment, configures Xdebug or Pcov, and generates ready-to-use launch configurations for your favorite IDE. Stop wrestling with `php.ini` and port mappings — just fly.

## Features

- **Environment Detection**: Automatically detects OS (macOS, Linux, Windows), Docker containers, and `php.ini` location.
- **Smart Configuration**:
  - Configures **Xdebug** for step debugging (mode `debug`, auto-client-host).
  - Configures **Pcov** for fast coverage.
  - Handles conflicts (e.g., disables Xdebug coverage when Pcov is active).
  - Idempotent — runs safely multiple times without duplicating config blocks.
- **Extension Management**:
  - **Check Status**: View installed/enabled status of Xdebug and Pcov clearly.
  - **Toggle**: Enable or disable extensions with a single command.
  - **Auto-Install**: Smartly detects missing extensions and offers to install them for you (macOS/Linux).
- **IDE Integration**: Generates project-specific config files for:
  - **VS Code** (`.vscode/launch.json`)
  - **PhpStorm** (`.idea/runConfigurations/XML`)
  - **Sublime Text** (`*.sublime-project`)
- **Health Checks**: Verifies your installation and reports actionable warnings (e.g., missing extensions, wrong modes).

## Installation

### Requirements
- PHP 8.1 or higher
- Composer

### Setup

```bash
git clone https://github.com/yourusername/php-debug-pilot.git
cd php-debug-pilot
composer install
```

> **Note**: This tool configures existing PHP extensions. Ensure you have `xdebug` or `pcov` installed (e.g., via `pecl`, `apt`, `brew`, or Dockerfile), or let the tool help you install them.

## Usage

### Interactive Setup
Run the setup wizard to detect checks, configure extensions, and generate IDE files:

```bash
php bin/debug-pilot
```

The tool will:
1.  Detect your environment.
2.  Ask which debugger you want to configure (Xdebug / Pcov).
3.  Ask which IDE you use.
4.  Write the configuration and verify the setup.

### Check Status
See which extensions are installed and enabled:

```bash
php bin/debug-pilot status
```

**Output:**
```
 ┌─────────┬───────────┬─────────┐
 │ Driver  │ Installed │ Enabled │
 ├─────────┼───────────┼─────────┤
 │ xdebug  │ ✅        │ ✅       │
 │ pcov    │ ✅        │ ❌       │
 └─────────┴───────────┴─────────┘
```

### Enable / Disable Extensions
Toggle extension state quickly without editing `php.ini` manually:

```bash
# Enable Xdebug
php bin/debug-pilot toggle xdebug

# Enable Pcov (and auto-disable Xdebug coverage)
php bin/debug-pilot toggle pcov
```

> **Note**: If the extension is not installed, the tool will offer to install it for you automatically (on supported systems).

### Non-Interactive Setup
Perfect for CI/CD pipelines or automated setup scripts.

```bash
# Configure Xdebug for VS Code
php bin/debug-pilot setup --debugger=xdebug --ide=vscode

# Configure Pcov for PhpStorm
php bin/debug-pilot setup --debugger=pcov --ide=phpstorm

# Custom host/port override
php bin/debug-pilot setup --debugger=xdebug --ide=vscode --host=192.168.1.5 --port=9000
```

### Setup Options

| Option | Description | Default |
|---|---|---|
| `-p`, `--project-path` | Root path of your project (where IDE config is written) | Current directory |
| `-d`, `--debugger` | Debugger to configure (`xdebug`, `pcov`) | *(Prompts user)* |
| `-i`, `--ide` | IDE to configure (`vscode`, `phpstorm`, `sublime`) | *(Auto-detects or prompts)* |
| `--host` | Xdebug client host IP | `auto` (detects Docker host or `localhost`) |
| `--port` | Xdebug client port | `9003` |

## Supported Platforms

- **OS**: macOS, Linux, Windows, Docker Containers.
- **Debuggers**: Xdebug 3+, Pcov.
- **IDEs**: VS Code, PhpStorm, Sublime Text 3/4.

## Troubleshooting

**"Extension is not installed" warning?**
Run `php bin/debug-pilot install <extension>` (or `toggle`) and follow the interactive prompts to install it. Or install manually:
- **macOS**: `pecl install xdebug`
- **Ubuntu**: `sudo apt install php-xdebug`
- **Docker**: `RUN pecl install xdebug && docker-php-ext-enable xdebug`

**"Cannot write to php.ini"?**
Run the tool with `sudo` if your `php.ini` is system-protected:
```bash
sudo php bin/debug-pilot setup
```
