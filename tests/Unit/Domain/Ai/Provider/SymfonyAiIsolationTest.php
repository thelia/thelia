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

namespace Thelia\Tests\Unit\Domain\Ai\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * The library stays an implementation detail: a swap of it must touch only Domain/Ai/Provider.
 */
final class SymfonyAiIsolationTest extends TestCase
{
    public function testNoClassOutsideTheProviderNamespaceTouchesTheLibrary(): void
    {
        $coreLibrary = \dirname(__DIR__, 5).'/core/lib/Thelia';
        $offenders = [];

        foreach ((new Finder())->files()->in($coreLibrary)->name('*.php')->notPath('Domain/Ai/Provider') as $file) {
            // The MCP bundle (Symfony\AI\McpBundle) is another library, registered by the kernel: only the platform one is isolated.
            if (1 === preg_match('/^\s*(use|namespace)\s+Symfony\\\\AI\\\\(?!McpBundle\\\\)|\\\\Symfony\\\\AI\\\\(?!McpBundle\\\\)|[\'"]Symfony\\\\\\\\AI\\\\\\\\(?!McpBundle)/m', $file->getContents())) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        self::assertSame([], $offenders);
    }

    public function testTheCheckSeesTheLibraryWhereItIs(): void
    {
        $inside = (new Finder())->files()->in(\dirname(__DIR__, 5).'/core/lib/Thelia/Domain/Ai/Provider')->name('*.php')->contains('use Symfony\AI\Platform\\');

        self::assertGreaterThan(5, iterator_count($inside), 'the provider adapters are the ones that use it');
    }
}
