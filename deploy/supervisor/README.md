# Supervisor Setup

This Supervisor config is a production-oriented example for Nạp Carot.

## Included Programs

`napcarot.conf` runs and monitors:

- two Laravel queue workers for `topup`, `mails`, `user-logs`, and `default`
- one Laravel scheduler process
- one Laravel Reverb WebSocket server bound to `127.0.0.1:8082`

## Before Enabling

Update these values to match your server:

- `directory=/var/www/napcarot.vn/laravel-app`
- PHP binary and absolute Artisan path in each `command`
- `user=www-data`
- `environment=HOME="/var/www",USER="www-data"`
- Reverb's internal port `8082` and the matching reverse-proxy upstream

Verify PHP and prepare writable log storage before enabling the config:

```bash
command -v php8.2
sudo mkdir -p /var/www/napcarot.vn/laravel-app/storage/logs
sudo chown -R www-data:www-data /var/www/napcarot.vn/laravel-app/storage /var/www/napcarot.vn/laravel-app/bootstrap/cache
```

## Install

Copy the file into Supervisor's config directory:

```bash
sudo cp deploy/supervisor/napcarot.conf /etc/supervisor/conf.d/napcarot.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

## Useful Commands

```bash
sudo supervisorctl restart napcarot:*
sudo supervisorctl tail -f napcarot:napcarot-worker_00
sudo supervisorctl tail -f napcarot:napcarot-scheduler
sudo supervisorctl tail -f napcarot:napcarot-reverb
```

## Cron Alternative

If you prefer classic cron for scheduling, remove `napcarot-scheduler` from
the `programs` line and remove its program block, then use:

```bash
* * * * * cd /var/www/napcarot.vn/laravel-app && /usr/bin/php8.2 artisan schedule:run --no-interaction >> /dev/null 2>&1
```

In that case, do not run the `napcarot-scheduler` program.

## Notes

- `numprocs=2` is a safe starting point. Increase it only when the queue starts backing up.
- Queue priority is `topup`, `mails`, `user-logs`, then `default`.
- `queue:work --timeout=60` must remain below every production queue `retry_after` value.
- Each worker restarts after one hour or at 256 MB to release long-lived process memory.
- Reverb listens only on `127.0.0.1:8082`; terminate TLS and proxy WebSocket traffic through Nginx or Apache.
- For high Reverb connection counts, set `minfds=10000` in the main `[supervisord]` configuration and restart Supervisor.
- Run `php artisan queue:restart` and `php artisan reverb:restart` after deployments.
- If you deploy to another path, update `directory`, command paths, environment values, and log paths before enabling Supervisor.
