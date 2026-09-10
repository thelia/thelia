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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\TagElement;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The tag vocabulary screen of the configuration.
 *
 * Guarded by its own admin resource: a profile allowed to rename or delete a tag
 * reaches every customer carrying it, which is a wider reach than editing one
 * customer, so the customer resource is deliberately not enough.
 */
final class TagConfigurationScreenTest extends WebIntegrationTestCase
{
    private const URL = '/admin/configuration/tags';

    private ?AdminSessionInjector $injector = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The screen lives in the back-office theme, a separate composer package.
        // Skipping rather than failing when the installed theme predates it: a
        // core test that hard-requires unreleased theme code turns this suite red
        // for a reason that has nothing to do with core, which is exactly how the
        // sale-targeting tests broke.
        if (!class_exists('BackOfficeDefaultTwigBundle\\Controller\\Configuration\\TagController')) {
            self::markTestSkipped('The installed back-office theme has no tag configuration screen.');
        }

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        // Nullable and guarded: tearDown() still runs after setUp() skipped the
        // test, and the injector was never built in that case.
        $this->injector?->clear();
        parent::tearDown();
    }

    public function testTheScreenIsServedToAnAdminHoldingTheTagResource(): void
    {
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::URL);
    }

    public function testTheScreenListsTheTagsWithTheirCustomerCount(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'Screen VIP', 'colorCode' => '#1A2B3C']);
        $customer = $factory->customer($factory->customerTitle());
        $factory->tagElement($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::URL);

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Screen VIP', $html);
        self::assertStringContainsString('#1A2B3C', $html, 'The colour is rendered as a swatch.');
    }

    /**
     * A stored colour that is not a colour must not reach the style attribute.
     * The screen re-checks on the way out, because a row written by an import or
     * a hand-run SQL statement never passed the API validator.
     *
     * The value is seven characters long on purpose, the width of the column: a
     * longer payload is refused by the column itself, which proves nothing about
     * the guard this test is here for.
     */
    public function testAStoredColourThatIsNotAColourIsNotRendered(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'Screen injected']);
        $tag->setColorCode('#GGGGGG')->save($this->getPropelConnection());

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::URL);

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Screen injected', $html, 'The tag itself is still listed.');
        self::assertStringNotContainsString('#GGGGGG', $html, 'A value that is not a colour never reaches the style attribute.');
    }

    public function testAnAdminWithoutTheTagResourceIsRefused(): void
    {
        $customerOnlyAdmin = $this->factory()->restrictedAdmin([
            AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::UPDATE],
        ]);
        $this->loginAs($customerOnlyAdmin);

        $this->client->request('GET', self::URL);

        self::assertNotSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Being allowed on customers must not open the tag vocabulary.',
        );
    }

    private function factory(): FixtureFactory
    {
        // Deliberately not createFixtureFactory(): that helper pushes a synthetic
        // request when the stack is empty, which would then be the "main" request
        // the security context reads its session from. The admin would land in a
        // session nobody looks at and every page would answer a redirect.
        return new FixtureFactory($this->getPropelConnection());
    }

    private function loginAs(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector?->setAdmin($admin);
    }
}
