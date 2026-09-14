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

namespace Thelia\Tests\Unit\Install;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Install\Standalone\DistributionModuleDefaults;

/**
 * The distribution's composer.json is the one place that says which shipped modules
 * wait for the merchant. A project without the key, or without a composer.json at all,
 * has to behave as before: everything active.
 */
final class DistributionModuleDefaultsTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/thelia-distribution-defaults-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->workDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workDir);
    }

    public function testListedModulesAreDisabledByDefaultAndTheOthersAreNot(): void
    {
        $defaults = DistributionModuleDefaults::fromComposerJson($this->writeComposerJson(
            '{"name": "thelia/thelia-skeleton", "extra": {"thelia": {"modules-disabled-by-default": ["StripePayment", "PayPal"]}}}',
        ));

        self::assertTrue($defaults->isDisabledByDefault('StripePayment'));
        self::assertTrue($defaults->isDisabledByDefault('PayPal'));
        self::assertFalse($defaults->isDisabledByDefault('Cheque'));
        self::assertFalse($defaults->isDisabledByDefault('stripepayment'));
    }

    public function testAComposerJsonWithoutTheKeyDisablesNothing(): void
    {
        $defaults = DistributionModuleDefaults::fromComposerJson($this->writeComposerJson('{"name": "thelia/thelia-skeleton", "extra": {"thelia": {}}}'));

        self::assertFalse($defaults->isDisabledByDefault('StripePayment'));
    }

    public function testAMissingComposerJsonDisablesNothing(): void
    {
        $defaults = DistributionModuleDefaults::fromComposerJson($this->workDir.'/composer.json');

        self::assertFalse($defaults->isDisabledByDefault('StripePayment'));
    }

    #[DataProvider('malformedLists')]
    public function testAMalformedListIsRefusedInsteadOfBeingGuessed(string $json): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DistributionModuleDefaults::fromComposerJson($this->writeComposerJson($json));
    }

    /** @return iterable<string, array{string}> */
    public static function malformedLists(): iterable
    {
        yield 'a single string instead of a list' => ['{"extra": {"thelia": {"modules-disabled-by-default": "StripePayment"}}}'];
        yield 'a map instead of a list' => ['{"extra": {"thelia": {"modules-disabled-by-default": {"StripePayment": true}}}}'];
        yield 'a list holding something other than a code' => ['{"extra": {"thelia": {"modules-disabled-by-default": ["StripePayment", 3]}}}'];
    }

    private function writeComposerJson(string $json): string
    {
        $path = $this->workDir.'/composer.json';
        file_put_contents($path, $json);

        return $path;
    }
}
