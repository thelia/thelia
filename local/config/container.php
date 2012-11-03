<?php
if (is_file(__DIR__ . '/config_db.php')) {
    require __DIR__ . '/config_db.php';
} else {
    return false;
}

use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();

$container->register('database','Thelia\\Database\\Connection');

$container->register('http_kernel','Symfony\\Component\\HttpKernel\\HttpKernel');

$container->register('session','Symfony\\Component\\HttpFoundation\\Session\\Session');

return $container;
