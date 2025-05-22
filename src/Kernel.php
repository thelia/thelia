<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Thelia\Core\Thelia;

class Kernel extends Thelia
{
    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        foreach (Thelia::getTemplateBundlesDirectories() as $templatePath) {
            if (is_dir($templatePath)) {
                $bundleInfos = detectNamespaceFromBundle($templatePath);
                if ($bundleInfos === null || !isset($this->bundles[$bundleInfos['namespace']])) {
                    continue;
                }
                $bundleFQCN = $bundleInfos['namespace'].'\\'.$bundleInfos['class'];

                $namespaceParts = explode('\\', $bundleFQCN);
                array_pop($namespaceParts);
                $namespace = implode('\\', $namespaceParts).'\\';
                $container->services()
                    ->load($namespace, $templatePath)
                    ->autowire()
                    ->autoconfigure();
            }
        }

        $container->import('../config/{packages}/*.yaml');
        $container->import('../config/{packages}/'.$this->environment.'/*.yaml');

        if (is_file(\dirname(__DIR__).'/config/services.yaml')) {
            $container->import('../config/services.yaml');
            $container->import('../config/{services}_'.$this->environment.'.yaml');

            return;
        }

        $path = \dirname(__DIR__).'/config/services.php';

        if (is_file($path)) {
            (require $path)($container->withPath($path), $this);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        parent::configureRoutes($routes);

        $routes->import('../config/{routes}/'.$this->environment.'/*.yaml');
        $routes->import('../config/{routes}/*.yaml');

        if (is_file(\dirname(__DIR__).'/config/routes.yaml')) {
            $routes->import('../config/routes.yaml');

            return;
        }

        $path = \dirname(__DIR__).'/config/routes.php';

        if (is_file($path)) {
            (require $path)($routes->withPath($path), $this);
        }
    }
}
function detectNamespaceFromBundle(string $directory): ?array
{
    $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

    foreach ($rii as $file) {
        if ($file->isDir()) {
            continue;
        }

        if (!str_ends_with($file->getFilename(), 'Bundle.php')) {
            continue;
        }

        $content = file($file->getPathname());
        $namespace = null;
        $class = null;

        foreach ($content as $line) {
            if (str_starts_with(trim($line), 'namespace ')) {
                $namespace = trim(str_replace(['namespace', ';'], '', $line));
            }

            if (preg_match('/class\s+([^\s]+)/', $line, $matches)) {
                $class = $matches[1];
            }

            if ($namespace && $class) {
                return [
                    'namespace' => $namespace,
                    'class' => $class,
                ];
            }
        }
    }

    return null;
}
