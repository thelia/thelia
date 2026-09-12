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

namespace Thelia\Tests\Http\BackOffice;

use BackOfficeDefaultTwigBundle\Service\Coupon\CouponTriggerModeInput;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Condition\Implementation\MatchDeliveryModules;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The coupon editor must offer the delivery-method condition with its own
 * fragment (BackOfficeDefaultTwigBundle\Service\Coupon\CouponConditionsRenderer
 * mapping Thelia\Condition\Implementation\MatchDeliveryModules), not the
 * "unsupported condition" placeholder every unmapped condition falls back to.
 */
final class CouponDeliveryModulesConditionTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever back-office
        // theme it is given, and one that predates the automatic promotions has
        // no fragment for this condition.
        if (!class_exists(CouponTriggerModeInput::class)) {
            self::markTestSkipped('The installed back-office theme predates the automatic promotions.');
        }

        parent::setUp();

        $this->skipUnlessTwigBackOffice();

        $this->injector = new AdminSessionInjector();

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($this->injector);
    }

    /**
     * The class of the theme is on the autoloader as soon as it is installed,
     * whether or not it is the template the store actually runs: the fragment
     * asserted here is only served when it is the active one.
     */
    private function skipUnlessTwigBackOffice(): void
    {
        $template = static::getContainer()->getParameter('thelia_admin_template');

        if ('default-twig' !== $template) {
            self::markTestSkipped(\sprintf(
                'The installed back-office template is "%s", not "default-twig".',
                \is_string($template) ? $template : 'unknown',
            ));
        }
    }

    protected function tearDown(): void
    {
        // setUp() may have skipped before wiring the injector.
        if (isset($this->injector)) {
            $this->injector->clear();
        }
        parent::tearDown();
    }

    private function loginAdmin(): void
    {
        // FixtureFactory built directly (not via createFixtureFactory()): see
        // CouponTriggerModeTest::loginAdmin() for why that helper cannot be used.
        $factory = new FixtureFactory($this->getPropelConnection());

        $admin = $factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    public function testTheDeliveryMethodConditionHasItsOwnInputs(): void
    {
        $this->loginAdmin();

        $this->client->request('GET', '/admin/coupon/draw/read/conditionInputs/thelia.condition.match_delivery_modules');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('coupon-delivery-modules-select', $content);
        self::assertStringContainsString(MatchDeliveryModules::MODULES_LIST.'[value][]', $content);
        self::assertStringContainsString(MatchDeliveryModules::MODULES_LIST.'[operator]', $content);
        self::assertStringNotContainsString(
            'The Twig editor does not support this condition type yet',
            $content,
            'The delivery-method condition must be mapped, not fall back to the unsupported placeholder.',
        );
    }
}
