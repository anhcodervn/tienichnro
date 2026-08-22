# Supervisor Setup

This Supervisor config is a production-oriented example for Nạp Carot.

## Included Programs

- `napcarot.conf` runs and monitors:
  - two Laravel queue workers for `topup`, `mails`, `user-logs`, and `default`
  - one Laravel scheduler process
  - one Laravel Reverb WebSocket server

## Before Enabling

Update these values to match your server:

- `directory=/var/www/napcarot.vn/laravel-app`
- `command=/usr/bin/php ...`
- `user=www-data`
- log paths under `/var/log/supervisor/`

## Install

Copy the files into Supervisor's config directory:

```bash
sudo cp deploy/supervisor/napcarot.conf /etc/supervisor/conf.d/napcarot.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

## Useful Commands

```bash
sudo supervisorctl restart napcarot:*
sudo supervisorctl tail -f napcarot-worker
sudo supervisorctl tail -f napcarot-scheduler
sudo supervisorctl tail -f napcarot-reverb
```

## Cron Alternative

If you prefer classic cron for scheduling, remove `napcarot-scheduler` from
the `programs` line and remove its program block, then use:

```bash
* * * * * cd /var/www/napcarot.vn/laravel-app && php artisan schedule:run >> /dev/null 2>&1
```

In that case, do not run the `napcarot-scheduler` program.

## Notes

- `numprocs=2` is a safe starting point. Increase if queue starts backing up.
- `queue:work --timeout=60` stays below the default queue `retry_after` value of 90 seconds.
- Run `php artisan queue:restart` and `php artisan reverb:restart` after deployments.
- If you deploy to another path, update `directory` and log file names before enabling Supervisor.
