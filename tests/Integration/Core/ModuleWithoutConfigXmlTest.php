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

namespace Thelia\Tests\Integration\Core;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Model\Module;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Kernel\ModulesConfigurationKernel;

/**
 * config.xml is optional in a module: hooks, loops, forms and services are
 * discovered without it. The container of a shop holding such a module must
 * still compile.
 */
final class ModuleWithoutConfigXmlTest extends IntegrationTestCase
{
    private const CODE = 'ConfigXmlLessProbe';

    private string $moduleDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleDirectory = THELIA_LOCAL_MODULE_DIR.self::CODE;

        (new Filesystem())->mkdir($this->moduleDirectory.DS.'Config');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->moduleDirectory);

        parent::tearDown();
    }

    #[Test]
    public function aModuleWithoutConfigXmlIsLoaded(): void
    {
        self::assertFileDoesNotExist($this->moduleDirectory.DS.'Config'.DS.'config.xml');

        $container = new ContainerBuilder();

        $this->loadModulesConfiguration($container, $this->moduleWithoutConfigXml());

        self::assertTrue($container->hasDefinition('module.'.self::CODE));
    }

    #[Test]
    public function theTemplatesOfAModuleWithoutConfigXmlAreRegistered(): void
    {
        (new Filesystem())->mkdir($this->moduleDirectory.DS.'templates');

        $container = new ContainerBuilder();
        $container->registerExtension(new TwigExtension());

        $this->loadModulesConfiguration($container, $this->moduleWithoutConfigXml());

        self::assertSame(
            [$this->moduleDirectory.DS.'templates' => self::CODE.'Module'],
            $container->getExtensionConfig('twig')[0]['paths'] ?? null,
        );
    }

    #[Test]
    public function aConfigXmlThatExistsIsStillRead(): void
    {
        (new Filesystem())->dumpFile(
            $this->moduleDirectory.DS.'Config'.DS.'config.xml',
            <<<'XML'
                <?xml version="1.0" encoding="UTF-8" ?>
                <config xmlns="http://thelia.net/schema/dic/config"
                        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                        xsi:schemaLocation="http://thelia.net/schema/dic/config http://thelia.net/schema/dic/config/thelia-1.0.xsd">
                    <services>
                        <service id="config.xml.probe" class="stdClass"/>
                    </services>
                </config>
                XML,
        );

        $container = new ContainerBuilder();

        $this->loadModulesConfiguration($container, $this->moduleWithoutConfigXml());

        self::assertTrue($container->hasDefinition('config.xml.probe'));
    }

    /**
     * @param list<Module> $modules
     */
    private function loadModulesConfiguration(ContainerBuilder $container, array $modules): void
    {
        (new ModulesConfigurationKernel('test', false))->loadFor($container, $modules);
    }

    /**
     * @return list<Module>
     */
    private function moduleWithoutConfigXml(): array
    {
        $moduleClass = new class extends BaseModule {};

        return [(new Module())->setCode(self::CODE)->setFullNamespace($moduleClass::class)];
    }
}
