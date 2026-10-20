# OPcache preloading

PHP can compile the classes a shop uses on every request once, when PHP-FPM starts, and keep them in shared memory (`opcache.preload`, PHP 7.4 and later). A Thelia project ships the entry point Symfony expects, `config/preload.php`, which loads the preload file written with the production container (`var/cache/prod/App_KernelProdContainer.preload.php`).

That file registers the directory of the generated Propel models (`var/propel/prod/model`) before it loads anything else: the loops of the core and of the modules extend those models, which are not in the Composer autoloader.

## Enabling it

In the `php.ini` (or pool configuration) of PHP-FPM, with the absolute path of the project:

```ini
opcache.enable=1
opcache.preload=/var/www/shop/config/preload.php
; The user PHP-FPM runs as, required when the master process runs as root.
opcache.preload_user=www-data
```

On a host that sets it through an environment variable (`CC_OPCACHE_PRELOAD` on Clever Cloud), give the same path.

## Deploying with it

The production cache has to exist before PHP-FPM starts: warm it up during the deployment (`php Thelia cache:warmup --env=prod`), then restart PHP-FPM. Preloaded classes stay in memory until PHP-FPM restarts, so restart it after every deployment and after every production cache rebuild.
