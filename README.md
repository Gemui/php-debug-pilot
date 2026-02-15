# PHP Debug Pilot 🚀

**Zero-Configuration PHP Debugging Setup.**

PHP Debug Pilot is a CLI tool that automatically detects your environment, install, configures Xdebug or Pcov, and generates ready-to-use launch configurations for your favorite IDE. Stop wrestling with `php.ini` and port mappings — just fly.

---

## ✨ Features

- **Environment Detection** — Automatically detects OS (macOS, Linux, Windows), Docker containers, and `php.ini` location.
- **Smart Configuration**
  - Configures **Xdebug** for step debugging (mode `debug`, auto-client-host).
  - Configures **Pcov** for fast coverage.
  - Handles conflicts (e.g., disables Xdebug coverage when Pcov is active).
  - Idempotent — runs safely multiple times without duplicating config blocks.
- **Extension Management**
  - **Check Status** — View installed/enabled status of Xdebug and Pcov at a glance.
  - **Toggle** — Enable or disable extensions with a single command.
  - **Auto-Install** — Detects missing extensions and offers to install them for you (macOS/Linux).
- **IDE Integration** — Generates project-specific config files for:
  - **VS Code** (`.vscode/launch.json`)
  - **PhpStorm** (`.idea/runConfigurations/XML`)
  - **Sublime Text** (`*.sublime-project`)
- **Health Checks** — Verifies your installation and reports actionable warnings (e.g., missing extensions, wrong modes).

---

## 📦 Installation

### Requirements
- PHP 8.2 or higher
- Composer

### Install Globally (Recommended)

Install once and use `debug-pilot` from anywhere:

```bash
composer global require gemui/php-debug-pilot
```

> Make sure Composer's global `vendor/bin` directory is in your system `PATH`.
> Typically this is `~/.composer/vendor/bin` or `~/.config/composer/vendor/bin`.
>
> Add it to your shell profile if you haven't already:
> ```bash
> export PATH="$HOME/.composer/vendor/bin:$PATH"
> ```

After installing globally, you can use `debug-pilot` directly:

```bash
debug-pilot              # Interactive setup wizard
debug-pilot status       # Extension status table
debug-pilot toggle xdebug
```

### Install Per-Project

Or add it as a dev dependency to a specific project:

```bash
composer require --dev gemui/php-debug-pilot
```

Then use it via Composer's local bin:

```bash
./vendor/bin/debug-pilot
```

---

## 🚀 Usage

### Interactive Setup
Run the setup wizard to configure extensions and generate IDE files:

```bash
debug-pilot
```

The tool will:
1. Detect your environment.
2. Ask which debugger you want to configure (Xdebug / Pcov).
3. Ask which IDE you use.
4. Write the configuration and verify the setup.

### Check Status
See which extensions are installed and enabled:

```bash
debug-pilot status
```

**Output:**
```
 ┌─────────┬───────────┬─────────┐
 │ Driver  │ Installed │ Enabled │
 ├─────────┼───────────┼─────────┤
 │ xdebug  │ ✅         │ ✅       │
 │ pcov    │ ✅         │ ❌       │
 └─────────┴───────────┴─────────┘
```

### Enable / Disable Extensions
Toggle extension state without editing `php.ini` manually:

```bash
# Enable Xdebug
debug-pilot toggle xdebug

# Enable Pcov (and auto-disable Xdebug coverage)
debug-pilot toggle pcov
```

> If the extension is not installed, the tool will offer to install it for you automatically (on supported systems).

### Install Extensions
Manually install a specific extension:

```bash
# Install Xdebug
debug-pilot install xdebug

# Install Pcov
debug-pilot install pcov
```

> The tool will automatically detect if auto-installation is supported on your system and run the appropriate installation command. If auto-installation is not available (e.g., Docker, Windows), it will display manual installation instructions.

### Non-Interactive Setup
Perfect for CI/CD pipelines or automated setup scripts:

```bash
# Configure Xdebug for VS Code
debug-pilot setup --debugger=xdebug --ide=vscode

# Configure Pcov for PhpStorm
debug-pilot setup --debugger=pcov --ide=phpstorm

# Custom host/port override
debug-pilot setup --debugger=xdebug --ide=vscode --host=192.168.1.5 --port=9000

# Configure specific Xdebug modes
debug-pilot setup --debugger=xdebug --ide=vscode --xdebug-mode=debug,develop,coverage
```

### Setup Options

| Option | Description | Default |
|---|---|---|
| `-p`, `--project-path` | Root path of your project (where IDE config is written) | Current directory |
| `-d`, `--debugger` | Debugger to configure (`xdebug`, `pcov`) | *(Prompts user)* |
| `-i`, `--ide` | IDE to configure (`vscode`, `phpstorm`, `sublime`) | *(Auto-detects or prompts)* |
| `--host` | Xdebug client host IP | `auto` (detects Docker host or `localhost`) |
| `--port` | Xdebug client port | `9003` |
| `--xdebug-mode` | Xdebug modes, comma-separated (`debug`, `develop`, `coverage`, `profile`, `trace`) | *(Prompts user with current modes pre-selected)* |

---

## 🖥️ Supported Platforms

- **OS**: macOS, Linux, Windows, Docker Containers.
- **Debuggers**: Xdebug 3+, Pcov.
- **IDEs**: VS Code, PhpStorm, Sublime Text 3/4.

---

## 🔧 Troubleshooting

**"Extension is not installed" warning?**
Run `debug-pilot install <extension>` (or `toggle`) and follow the interactive prompts to install it. Or install manually:
- **macOS**: `pecl install xdebug`
- **Ubuntu**: `sudo apt install php-xdebug`
- **Docker**: `RUN pecl install xdebug && docker-php-ext-enable xdebug`

**"Cannot write to php.ini"?**
Run the tool with `sudo` if your `php.ini` is system-protected:
```bash
sudo debug-pilot setup
```

---

## 📄 License

MIT
