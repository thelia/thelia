<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Service\Composer;

class ComposerHelper
{
    /**
     * @throws \JsonException
     */
    public function getComposerPackagesFromPath(string $path): array
    {
        $composerJsonPath = rtrim($path, '/').'/composer.json';
        if (!file_exists($composerJsonPath)) {
            throw new \InvalidArgumentException("No composer.json find in '$path'");
        }

        return json_decode(file_get_contents($composerJsonPath), true, 512, \JSON_THROW_ON_ERROR);
    }

    public function addNamespaceToBundlesSymfony(string $namespace, array $environnement): void
    {
        $bundlesPath = THELIA_ROOT.'config/bundles.php';
        if (!file($bundlesPath)) {
            throw new \InvalidArgumentException("No bundles.php file found in '$bundlesPath'");
        }
        $formatedNamespace = $namespace.'::class';
        $bundles = require $bundlesPath;
        if (isset($bundles[$formatedNamespace])) {
            return;
        }
        $bundles[$formatedNamespace] = $environnement;
        file_put_contents($bundlesPath, '<?php return '.var_export($bundles, true).';');
    }

    public function findFirstClassBundle(string $directory): ?string
    {
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($rii as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)
                && preg_match('/class\s+(\w+)/', $content, $classMatch)) {
                $namespace = trim($nsMatch[1]);
                $className = trim($classMatch[1]);

                return $namespace.'\\'.$className;
            }
        }

        return null;
    }
}
