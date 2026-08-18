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

namespace Thelia\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Request::get() is deprecated since Symfony 7.4 and searches the attributes, the query
 * string and the request body in that order, so a call site that meant to read one of
 * them silently accepts the other two. The core reads every request value from an
 * explicit bag instead; this test keeps it that way.
 *
 * A single reachable call site also writes one deprecation line per request into the
 * production error log, which is how a low-traffic shop reached tens of MB a day.
 */
final class ExplicitRequestBagTest extends TestCase
{
    private const CORE_LIBRARY_DIRECTORY = __DIR__.'/../../../core/lib';

    private const REQUEST_ACCESSORS = ['getRequest', 'getCurrentRequest', 'getMainRequest'];

    public function testRequestGetIsStillDeprecatedUpstream(): void
    {
        $method = new \ReflectionMethod(Request::class, 'get');

        self::assertStringContainsString(
            '@deprecated',
            (string) $method->getDocComment(),
            'Request::get() is no longer deprecated upstream, this guard can be dropped.',
        );
    }

    public function testCoreReadsRequestValuesFromAnExplicitBag(): void
    {
        $callSites = [];

        foreach ($this->coreSourceFiles() as $file) {
            foreach ($this->findRequestGetCalls($file) as $line) {
                $callSites[] = $file->getPathname().':'.$line;
            }
        }

        self::assertSame(
            [],
            $callSites,
            "Request::get() is deprecated since Symfony 7.4. Read from the bag the value actually\n"
            ."comes from — query->get(), request->get() or attributes->get() — at:\n"
            .implode("\n", $callSites),
        );
    }

    /**
     * @return iterable<\SplFileInfo>
     */
    private function coreSourceFiles(): iterable
    {
        $directory = realpath(self::CORE_LIBRARY_DIRECTORY);

        self::assertIsString($directory, 'The core library directory was not found.');

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ('php' === $file->getExtension()) {
                yield $file;
            }
        }
    }

    /**
     * Walks the token stream so that comments and strings mentioning the call cannot
     * trip the guard, and reports the line of every `->get(` whose receiver is a
     * request: a `$…request` variable, or one of the usual request accessors.
     *
     * @return list<int>
     */
    private function findRequestGetCalls(\SplFileInfo $file): array
    {
        $tokens = array_values(array_filter(
            token_get_all((string) file_get_contents($file->getPathname())),
            static fn (array|string $token): bool => !\is_array($token)
                || !\in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true),
        ));

        $lines = [];

        foreach ($tokens as $index => $token) {
            if (!\is_array($token) || \T_STRING !== $token[0] || 'get' !== $token[1]) {
                continue;
            }

            if (($tokens[$index + 1] ?? null) !== '(') {
                continue;
            }

            $operator = $tokens[$index - 1] ?? null;

            if (!\is_array($operator)
                || !\in_array($operator[0], [\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR], true)) {
                continue;
            }

            if ($this->isRequestReceiver($tokens, $index - 2)) {
                $lines[] = $token[2];
            }
        }

        return $lines;
    }

    /**
     * The receiver sits just before the `->`. Two shapes count as a request:
     * a `$…request` variable, and a `getRequest()`-style accessor call. Anything
     * else — `$request->query`, `$this->request`, `$container` — does not, so the
     * explicit bags and the unrelated `get()` methods stay untouched.
     *
     * @param list<array{int, string, int}|string> $tokens
     */
    private function isRequestReceiver(array $tokens, int $index): bool
    {
        $token = $tokens[$index] ?? null;

        if (\is_array($token) && \T_VARIABLE === $token[0]) {
            return str_ends_with(strtolower($token[1]), 'request');
        }

        if (')' !== $token || '(' !== ($tokens[$index - 1] ?? null)) {
            return false;
        }

        $accessor = $tokens[$index - 2] ?? null;

        return \is_array($accessor)
            && \T_STRING === $accessor[0]
            && \in_array($accessor[1], self::REQUEST_ACCESSORS, true);
    }
}
