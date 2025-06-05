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
        $bundles = require $bundlesPath;
        if (!isset($bundles[$namespace])) {
            $bundles[$namespace] = $environnement;
            ksort($bundles);
        }
        file_put_contents($bundlesPath, $this->dumpBundlesPhp($bundles));
    }

    public function findFirstClassBundle(string $directory): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getRealPath());
            if (!$content) {
                continue;
            }

            if (!preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
                continue;
            }

            if (!preg_match('/class\s+(\w+Bundle)\b/', $content, $classMatch)) {
                continue;
            }

            return $nsMatch[1] . '\\' . $classMatch[1];
        }

        return null;
    }

    public function addPsr4NamespaceToComposer(
        string $bundleNamespace,
        string $path
    ): void
    {
        $composerJsonPath = THELIA_ROOT . 'composer.json';

        if (!file_exists($composerJsonPath)) {
            throw new \InvalidArgumentException("No composer.json found at '$composerJsonPath'");
        }

        try {
            $composerData = json_decode(file_get_contents($composerJsonPath), true, 512, \JSON_THROW_ON_ERROR);

            $namespaceParts = explode('\\', $bundleNamespace);
            array_pop($namespaceParts);
            $baseNamespace = implode('\\', $namespaceParts) . '\\';

            if (!isset($composerData['autoload']['psr-4'][$baseNamespace])) {
                $path = str_replace(THELIA_ROOT, '', $path);
                $composerData['autoload']['psr-4'][$baseNamespace] = $path. DS.'src'.DS;

                ksort($composerData['autoload']['psr-4']);

                file_put_contents(
                    $composerJsonPath,
                    json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                );
            }
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException("Invalid JSON in composer.json: " . $e->getMessage());
        }
    }


    private function dumpBundlesPhp(array $bundles): string
    {
        $lines = ["<?php\n", "return [\n"];

        foreach ($bundles as $fqcn => $envs) {
            $envParts = [];
            foreach ($envs as $env => $enabled) {
                $envParts[] = "'$env' => " . ($enabled ? 'true' : 'false');
            }

            $line = "    $fqcn::class => [" . implode(', ', $envParts) . "],\n";
            $lines[] = $line;
        }

        $lines[] = "];\n";

        return implode('', $lines);
    }
}
