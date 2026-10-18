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

namespace Thelia\Tests\Integration\Module;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\File\Exception\FileNotFoundException;
use Thelia\Module\Validator\ModuleValidator;
use Thelia\Test\IntegrationTestCase;

/**
 * Only Config/module.xml is required in a module: config.xml is optional, so a
 * module without it must pass the validation run before it is installed,
 * activated or deactivated.
 */
final class ModuleValidatorWithoutConfigXmlTest extends IntegrationTestCase
{
    private string $modulePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulePath = sys_get_temp_dir().'/thelia-module-validator-'.bin2hex(random_bytes(4)).'/ValidatorProbe';

        (new Filesystem())->dumpFile($this->modulePath.'/Config/module.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <module xmlns="http://thelia.net/schema/dic/module"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xsi:schemaLocation="http://thelia.net/schema/dic/module http://thelia.net/schema/dic/module/module-2_2.xsd">
                <fullnamespace>ValidatorProbe\ValidatorProbe</fullnamespace>
                <descriptive locale="en_US">
                    <title>Validator probe</title>
                </descriptive>
                <languages>
                    <language>en_US</language>
                </languages>
                <version>1.0.0</version>
                <type>classic</type>
                <thelia>3.0.0</thelia>
                <stability>prod</stability>
                <mandatory>0</mandatory>
                <hidden>0</hidden>
            </module>
            XML);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(\dirname($this->modulePath));

        parent::tearDown();
    }

    #[Test]
    public function aModuleWithoutConfigXmlPassesTheValidation(): void
    {
        self::assertFileDoesNotExist($this->modulePath.'/Config/config.xml');

        $validator = new ModuleValidator($this->modulePath);
        $validator->validate(false);

        self::assertSame('ValidatorProbe\ValidatorProbe', $validator->getModuleDefinition()->getNamespace());
    }

    #[Test]
    public function aModuleWithoutModuleXmlIsStillRefused(): void
    {
        (new Filesystem())->remove($this->modulePath.'/Config/module.xml');

        $this->expectException(FileNotFoundException::class);

        new ModuleValidator($this->modulePath);
    }
}
