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

namespace Thelia\Tests\Support\Filesystem;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Removes what a test wrote under var/, and nothing else.
 *
 * A path rendered by the code under test must never reach a recursive removal: a mutation of
 * that code (or a bug) turns it into the project root. Tests hard-code the folder they clean
 * and pass it here, where anything that is not strictly below var/ is refused.
 */
final class TestVarCleaner
{
    private function __construct()
    {
    }

    public static function remove(string $path): void
    {
        $var = rtrim(THELIA_ROOT, '/').'/var';

        if (!self::isStrictlyBelow(self::normalize($path), $var)) {
            throw new \LogicException(\sprintf('Refusing to remove "%s": a test only removes a path strictly below "%s/".', $path, $var));
        }

        $resolved = realpath($path);

        if (false !== $resolved && !self::isStrictlyBelow($resolved, (string) realpath($var))) {
            throw new \LogicException(\sprintf('Refusing to remove "%s": it resolves outside of "%s/".', $path, $var));
        }

        (new Filesystem())->remove($path);
    }

    private static function isStrictlyBelow(string $path, string $directory): bool
    {
        return \strlen($path) > \strlen($directory) + 1 && str_starts_with($path, $directory.'/');
    }

    private static function normalize(string $path): string
    {
        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ('..' === $part) {
                array_pop($parts);
            } elseif ('' !== $part && '.' !== $part) {
                $parts[] = $part;
            }
        }

        return ('/' === ($path[0] ?? '') ? '/' : '').implode('/', $parts);
    }
}
