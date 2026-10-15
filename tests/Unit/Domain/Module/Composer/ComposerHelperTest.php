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

namespace Thelia\Tests\Unit\Domain\Module\Composer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Domain\Module\Composer\ComposerHelper;

/**
 * Composer starts with `#!/usr/bin/env php`: run as a command, it takes the first php of the
 * PATH, which on a host with several PHP versions is not the one running Thelia.
 */
final class ComposerHelperTest extends TestCase
{
    private string $directory;
    private string|false $originalPath;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/thelia-composer-helper-'.bin2hex(random_bytes(4));
        mkdir($this->directory.'/bin', 0o777, true);
        $this->originalPath = getenv('PATH');
    }

    protected function tearDown(): void
    {
        false === $this->originalPath ? putenv('PATH') : putenv('PATH='.$this->originalPath);
        (new Filesystem())->remove($this->directory);
    }

    public function testComposerRunsWithThePhpBinaryOfTheCurrentProcess(): void
    {
        // An older php comes first in the PATH, as the default CLI of a shared host does.
        $this->writeScript($this->directory.'/bin/php', "#!/bin/sh\necho 'You are running 8.2.33' >&2\nexit 1\n");
        putenv('PATH='.$this->directory.'/bin:'.$this->originalPath);
        $composer = $this->writeScript($this->directory.'/composer', "#!/usr/bin/env php\n<?php\nexit(0);\n");

        (new ComposerHelper($composer))->dumpAutoload();

        $this->addToAssertionCount(1);
    }

    public function testAFailedDumpThrowsWithTheComposerOutput(): void
    {
        $composer = $this->writeScript($this->directory.'/composer', "#!/usr/bin/env php\n<?php\nfwrite(STDERR, \"Cannot scan vendor\\n\");\nexit(1);\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot scan vendor');

        (new ComposerHelper($composer))->dumpAutoload();
    }

    private function writeScript(string $path, string $content): string
    {
        file_put_contents($path, $content);
        chmod($path, 0o755);

        return $path;
    }
}
