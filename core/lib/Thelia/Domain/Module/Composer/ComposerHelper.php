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

namespace Thelia\Domain\Module\Composer;

class ComposerHelper
{
    /**
     * @throws \JsonException
     */
    public function getComposerPackagesFromPath(string $path): array
    {
        $composerJsonPath = rtrim($path, '/').'/composer.json';

        if (!file_exists($composerJsonPath)) {
            throw new \InvalidArgumentException(\sprintf("No composer.json find in '%s'", $path));
        }

        return json_decode(file_get_contents($composerJsonPath), true, 512, \JSON_THROW_ON_ERROR);
    }

    public function addNamespaceToBundlesSymfony(string $namespace, array $environnement): void
    {
        $bundlesPath = THELIA_ROOT.'config/bundles.php';

        if (!file($bundlesPath)) {
            throw new \InvalidArgumentException(\sprintf("No bundles.php file found in '%s'", $bundlesPath));
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
            new \RecursiveDirectoryIterator($directory),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || 'php' !== $file->getExtension()) {
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

            return $nsMatch[1].'\\'.$classMatch[1];
        }

        return null;
    }

    public function addPsr4NamespaceToComposer(
        string $bundleNamespace,
        string $path,
    ): void {
        $composerJsonPath = THELIA_ROOT.'composer.json';

        if (!file_exists($composerJsonPath)) {
            throw new \InvalidArgumentException(\sprintf("No composer.json found at '%s'", $composerJsonPath));
        }

        try {
            $composerData = json_decode(file_get_contents($composerJsonPath), true, 512, \JSON_THROW_ON_ERROR);

            $namespaceParts = explode('\\', $bundleNamespace);
            array_pop($namespaceParts);
            $baseNamespace = implode('\\', $namespaceParts).'\\';

            if (!isset($composerData['autoload']['psr-4'][$baseNamespace])) {
                $path = str_replace(THELIA_ROOT, '', $path);
                $composerData['autoload']['psr-4'][$baseNamespace] = $path.DS.'src'.DS;

                ksort($composerData['autoload']['psr-4']);

                file_put_contents(
                    $composerJsonPath,
                    json_encode($composerData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)."\n",
                );
            }
        } catch (\JsonException $jsonException) {
            throw new \InvalidArgumentException('Invalid JSON in composer.json: '.$jsonException->getMessage(), $jsonException->getCode(), $jsonException);
        }
    }

    public function findInstalledPackagePathByTypeAndInstallerName(string $packageType, string $installerName): ?string
    {
        $installedJsonPath = THELIA_ROOT.'vendor/composer/installed.json';

        if (!is_file($installedJsonPath)) {
            return null;
        }

        try {
            $installed = json_decode((string) file_get_contents($installedJsonPath), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $packages = $installed['packages'] ?? $installed;

        if (!\is_array($packages)) {
            return null;
        }

        foreach ($packages as $package) {
            if (!\is_array($package)) {
                continue;
            }

            if ((($package['type'] ?? null) !== $packageType)
                && isset($package['keywords'])
                && \is_array($package['keywords'])
                && !\in_array($packageType, $package['keywords'], true)
            ) {
                continue;
            }

            $extra = $package['extra'] ?? null;
            if (!\is_array($extra)) {
                continue;
            }

            if (($extra['installer-name'] ?? null) !== $installerName) {
                continue;
            }

            $installPath = $package['install-path'] ?? null;
            if (!\is_string($installPath) || '' === trim($installPath)) {
                continue;
            }

            $resolvedPath = $this->resolveInstalledJsonPath($installPath);

            return is_dir($resolvedPath) ? $resolvedPath : null;
        }

        return null;
    }

    private function resolveInstalledJsonPath(string $installPath): string
    {
        $baseDir = THELIA_ROOT.'vendor/composer';

        if ($this->isAbsolutePath($installPath)) {
            return $installPath;
        }

        $fullPath = $baseDir.DS.$installPath;
        $realPath = realpath($fullPath);

        return false === $realPath ? $fullPath : $realPath;
    }

    private function isAbsolutePath(string $path): bool
    {
        if ('' === $path) {
            return false;
        }

        if ('/' === $path[0] || '\\' === $path[0]) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }

    private function dumpBundlesPhp(array $bundles): string
    {
        $lines = ["<?php\n", "return [\n"];

        foreach ($bundles as $fqcn => $envs) {
            $envParts = [];

            foreach ($envs as $env => $enabled) {
                $envParts[] = \sprintf("'%s' => ", $env).($enabled ? 'true' : 'false');
            }

            $line = \sprintf('    %s::class => [', $fqcn).implode(', ', $envParts)."],\n";
            $lines[] = $line;
        }

        $lines[] = "];\n";

        return implode('', $lines);
    }
}
