# prun-shipping-scheduler
A FIO-based shipping scheduler

## Requirements

- PHP 8.5 or higher
- Composer 2.0 or higher

## Installation

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```

## Project Structure

This is a Symfony 8 application using the skeleton template. The project follows the standard Symfony directory structure:

- `bin/` - Console commands
- `config/` - Application configuration
- `public/` - Web root directory
- `src/` - Application source code
- `var/` - Cache and logs (not tracked in Git)
- `vendor/` - Composer dependencies (not tracked in Git)

## Development

Run the Symfony development server:
```bash
symfony server:start
```

Or use PHP's built-in server:
```bash
php -S localhost:8000 -t public/
```
