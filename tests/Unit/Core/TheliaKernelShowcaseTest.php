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

use App\Kernel;
use PHPUnit\Framework\TestCase;

/**
 * The component showcase is registered by the core when the shop installs it: the Flex recipe of the skeleton
 * writes config/bundles.php of a project once, and a shop created before the package would never get its line.
 */
final class TheliaKernelShowcaseTest extends TestCase
{
    private const string SHOWCASE_BUNDLE = 'OpenStudio\UxShowcaseBundle\OpenStudioUxShowcaseBundle';

    public function testTheShowcaseBundleIsRegisteredWhenThePackageIsInstalled(): void
    {
        if (!class_exists(self::SHOWCASE_BUNDLE)) {
            self::markTestSkipped('The component showcase is not installed.');
        }

        self::assertContains(self::SHOWCASE_BUNDLE, $this->bundleClasses(new Kernel('test', false)));
    }

    public function testTheShowcaseBundleIsLeftOutWhenThePackageIsAbsent(): void
    {
        $kernel = new class('test', false) extends Kernel {
            protected function isShowcaseInstalled(): bool
            {
                return false;
            }
        };

        self::assertNotContains(self::SHOWCASE_BUNDLE, $this->bundleClasses($kernel));
    }

    /**
     * @return list<class-string>
     */
    private function bundleClasses(Kernel $kernel): array
    {
        return array_map(static fn (object $bundle): string => $bundle::class, iterator_to_array($kernel->registerBundles(), false));
    }
}
