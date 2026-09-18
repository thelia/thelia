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

namespace Thelia\Tests\Integration\Core\Routing;

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Routing\TemplateAttributeLoader;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * A front template that declares a parent ships only what it overrides. Its own `src/` may
 * not even exist, and the routes its pages generate - `customer_login` and the rest - are
 * declared by the controllers of the template it inherits from.
 *
 * Reading the active template alone left those routes out of the collection entirely, and a
 * child template rendered its first page straight into "Unable to generate a URL for the
 * named route customer_login".
 */
final class ChildTemplateRoutesTest extends IntegrationTestCase
{
    private const ENV_OVERRIDE = 'ACTIVE_FRONT_TEMPLATE';
    private const PARENT_TEMPLATE = 'flexy';
    private const A_ROUTE_OF_THE_PARENT = 'customer_register';
    private const AN_OVERRIDABLE_ROUTE_OF_THE_PARENT = 'customer_login';

    private string $childTemplate;
    private string $childTemplateDirectory;
    private ?string $environmentOverride = null;
    private ?string $storedActiveTemplate = null;
    private ?\Closure $childAutoloader = null;

    protected function setUp(): void
    {
        parent::setUp();

        // An environment variable wins over the stored row in ConfigQuery::read(), and the
        // shipped .env pins the front template: without this the test would read that pin
        // back instead of the child template it just installed.
        $this->environmentOverride = $_ENV[self::ENV_OVERRIDE] ?? $_SERVER[self::ENV_OVERRIDE] ?? null;
        unset($_ENV[self::ENV_OVERRIDE], $_SERVER[self::ENV_OVERRIDE]);

        $this->storedActiveTemplate = ConfigQuery::read(
            TemplateDefinition::CONFIG_NAMES[TemplateDefinition::FRONT_OFFICE_SUBDIR],
        );

        $this->childTemplate = uniqid('thelia-child-template-', false);
        $this->childTemplateDirectory = THELIA_TEMPLATE_DIR
            .TemplateDefinition::FRONT_OFFICE_SUBDIR
            .DS.$this->childTemplate;
    }

    protected function tearDown(): void
    {
        if (null !== $this->childAutoloader) {
            spl_autoload_unregister($this->childAutoloader);
            $this->childAutoloader = null;
        }

        (new Filesystem())->remove($this->childTemplateDirectory);

        if (null !== $this->storedActiveTemplate) {
            ConfigQuery::write(
                TemplateDefinition::CONFIG_NAMES[TemplateDefinition::FRONT_OFFICE_SUBDIR],
                $this->storedActiveTemplate,
            );
        }

        if (null !== $this->environmentOverride) {
            $_ENV[self::ENV_OVERRIDE] = $_SERVER[self::ENV_OVERRIDE] = $this->environmentOverride;
        }

        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAChildTemplateWithoutControllersServesTheRoutesOfItsParent(): void
    {
        $this->installChildTemplate();

        $routes = (new TemplateAttributeLoader('test'))->load(null, 'template_attribute');

        self::assertNotNull(
            $routes->get(self::AN_OVERRIDABLE_ROUTE_OF_THE_PARENT),
            'A child template that ships no src/ must still serve the routes of its parent.',
        );
        self::assertNotNull($routes->get(self::A_ROUTE_OF_THE_PARENT));
    }

    public function testAControllerOfTheChildTemplateWinsOverTheRouteOfTheSameNameInItsParent(): void
    {
        $this->installChildTemplate();
        $this->installChildController('/a-login-page-of-its-own');

        $routes = (new TemplateAttributeLoader('test'))->load(null, 'template_attribute');

        self::assertSame(
            '/a-login-page-of-its-own',
            $routes->get(self::AN_OVERRIDABLE_ROUTE_OF_THE_PARENT)?->getPath(),
            'The nearest template in the chain declares the route that wins.',
        );
        self::assertNotNull(
            $routes->get(self::A_ROUTE_OF_THE_PARENT),
            'Overriding one route must not drop the other routes of the parent.',
        );
    }

    private function installChildTemplate(): void
    {
        $parent = self::PARENT_TEMPLATE;

        (new Filesystem())->dumpFile(
            $this->childTemplateDirectory.DS.'template.xml',
            <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <template xmlns="http://thelia.net/schema/dic/template">
                    <descriptive locale="en">
                        <title>A child template</title>
                    </descriptive>
                    <parent>{$parent}</parent>
                    <languages>
                        <language>en_US</language>
                    </languages>
                    <version>1.0.0</version>
                    <stability>prod</stability>
                </template>
                XML,
        );

        ConfigQuery::write(
            TemplateDefinition::CONFIG_NAMES[TemplateDefinition::FRONT_OFFICE_SUBDIR],
            $this->childTemplate,
        );
        ConfigQuery::resetCache();
    }

    /**
     * A template that ships controllers declares its own PSR-4 namespace, the way the parent
     * template declares FlexyBundle\. The test does the same for the throwaway one it writes.
     */
    private function installChildController(string $path): void
    {
        $namespace = 'TheliaChildTemplateTest'.bin2hex(random_bytes(6));
        $className = 'ChildLoginController';
        $sourceDirectory = $this->childTemplateDirectory.DS.'src';

        (new Filesystem())->dumpFile(
            $sourceDirectory.DS.$className.'.php',
            <<<PHP
                <?php

                declare(strict_types=1);

                namespace {$namespace};

                use Symfony\\Component\\HttpFoundation\\Response;
                use Symfony\\Component\\Routing\\Attribute\\Route;

                class {$className}
                {
                    #[Route('{$path}', name: 'customer_login', methods: ['GET'])]
                    public function login(): Response
                    {
                        return new Response();
                    }
                }
                PHP,
        );

        $this->childAutoloader = static function (string $class) use ($namespace, $sourceDirectory): void {
            if (!str_starts_with($class, $namespace.'\\')) {
                return;
            }

            require $sourceDirectory.DS.substr($class, \strlen($namespace) + 1).'.php';
        };

        spl_autoload_register($this->childAutoloader);
    }
}
