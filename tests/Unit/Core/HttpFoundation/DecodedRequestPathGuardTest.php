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

namespace Thelia\Tests\Unit\Core\HttpFoundation;

use PHPUnit\Framework\TestCase;
use Thelia\Core\HttpFoundation\RequestPath;

/**
 * The router and the firewall match a path they have percent-decoded once, while
 * Request::getPathInfo() hands it back as the client wrote it. A decision taken on the raw
 * path can be sidestepped by encoding one letter: `/api/%6Dcp` is routed to `/api/mcp`
 * and yet is not equal to it. The core reads the path through {@see RequestPath::decoded()};
 * this test keeps it that way.
 *
 * A new call to getPathInfo() fails here. Use RequestPath::decoded(), or, when the raw
 * spelling is really what the code needs, add the file below with the reason.
 */
final class DecodedRequestPathGuardTest extends TestCase
{
    private const CORE_LIBRARY_DIRECTORY = __DIR__.'/../../../../core/lib';

    /**
     * Files allowed to read the raw path, relative to core/lib, with the reason.
     */
    private const RAW_PATH_READERS = [
        'Thelia/Core/HttpFoundation/RequestPath.php' => 'the helper that decodes the path',
        'Thelia/Api/Service/API/RouteMatcherService.php' => 'hands the path to the router, which decodes it before matching',
        'Thelia/Api/Bridge/Propel/Filter/CustomFilters/FilterService.php' => 'looks up the filters a client asked for by the resource name; another spelling only finds none',
    ];

    public function testTheCoreDecidesOnTheDecodedPath(): void
    {
        $callSites = [];

        foreach ($this->coreSourceFiles() as $relativePath => $file) {
            if (isset(self::RAW_PATH_READERS[$relativePath])) {
                continue;
            }

            foreach ($this->findGetPathInfoCalls($file) as $line) {
                $callSites[] = $relativePath.':'.$line;
            }
        }

        self::assertSame(
            [],
            $callSites,
            "getPathInfo() returns the path as the client wrote it, percent-encoding included, while\n"
            ."the router matches it decoded. Compare RequestPath::decoded(\$request) instead, at:\n"
            .implode("\n", $callSites),
        );
    }

    public function testEveryAllowedFileStillReadsTheRawPath(): void
    {
        $directory = $this->coreLibraryDirectory();

        foreach (array_keys(self::RAW_PATH_READERS) as $relativePath) {
            $file = new \SplFileInfo($directory.'/'.$relativePath);

            self::assertTrue($file->isFile(), $relativePath.' no longer exists: remove it from the allowed files.');
            self::assertNotSame([], $this->findGetPathInfoCalls($file), $relativePath.' no longer reads the raw path: remove it from the allowed files.');
        }
    }

    /**
     * @return iterable<string, \SplFileInfo>
     */
    private function coreSourceFiles(): iterable
    {
        $directory = $this->coreLibraryDirectory();

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ('php' === $file->getExtension()) {
                yield substr($file->getPathname(), \strlen($directory) + 1) => $file;
            }
        }
    }

    private function coreLibraryDirectory(): string
    {
        $directory = realpath(self::CORE_LIBRARY_DIRECTORY);

        self::assertIsString($directory, 'The core library directory was not found.');

        return $directory;
    }

    /**
     * Walks the token stream, so that a comment or a string that mentions the method does
     * not count, and reports the line of every `->getPathInfo(` and `?->getPathInfo(`.
     *
     * @return list<int>
     */
    private function findGetPathInfoCalls(\SplFileInfo $file): array
    {
        $tokens = array_values(array_filter(
            token_get_all((string) file_get_contents($file->getPathname())),
            static fn (array|string $token): bool => !\is_array($token)
                || !\in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true),
        ));

        $lines = [];

        foreach ($tokens as $index => $token) {
            if (!\is_array($token) || \T_STRING !== $token[0] || 'getpathinfo' !== strtolower($token[1])) {
                continue;
            }

            $operator = $tokens[$index - 1] ?? null;

            if (\is_array($operator)
                && \in_array($operator[0], [\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR], true)
                && '(' === ($tokens[$index + 1] ?? null)) {
                $lines[] = $token[2];
            }
        }

        return $lines;
    }
}
