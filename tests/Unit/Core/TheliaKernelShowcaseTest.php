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

        self::assertArrayHasKey(self::SHOWCASE_BUNDLE, $this->coreBundles(new Kernel('test', false)));
    }

    public function testTheShowcaseBundleIsLeftOutWhenThePackageIsAbsent(): void
    {
        $kernel = new class('test', false) extends Kernel {
            protected function isShowcaseInstalled(): bool
            {
                return false;
            }
        };

        self::assertArrayNotHasKey(self::SHOWCASE_BUNDLE, $this->coreBundles($kernel));
    }

    /**
     * What the core registers, not what config/bundles.php of the application adds: a Flex recipe may write
     * the showcase there, as it does on a fresh install without a lock file.
     *
     * @return array<class-string, array<string, bool>>
     */
    private function coreBundles(Kernel $kernel): array
    {
        return (new \ReflectionMethod($kernel, 'coreBundles'))->invoke($kernel);
    }
}
