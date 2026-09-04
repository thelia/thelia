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

use BackOfficeDefaultTwigBundle\Service\Customer\CustomerFilters;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\ProductQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Back-office volet of the guest checkout US: the "Requires an account (no
 * guest checkout)" flag on a product's fiche. See
 * BackOfficeDefaultTwigBundle\Form\Product\ProductType and
 * Thelia\Core\Event\Product\ProductUpdateEvent::setGuestCheckoutForbidden(),
 * applied by Thelia\Action\Product::update().
 *
 * The product edit page (GET /admin/products/update) eagerly renders every
 * tab through sub-requests (attributes, combinations, images, documents),
 * one of which 404s for a bare-minimum FixtureFactory::product() regardless
 * of this feature — a pre-existing fragility of that page, out of this
 * task's scope. This test posts the save action directly instead of driving
 * it through that page's rendered form, so it exercises the field this US
 * actually added without depending on the unrelated fragility.
 */
final class ProductGuestCheckoutForbiddenTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever back-office
        // theme it is given, and one that predates the guest checkout has none of
        // the screens this asserts on.
        if (!class_exists(CustomerFilters::class) || !\defined(CustomerFilters::class.'::KEY_GUEST')) {
            self::markTestSkipped('The installed back-office theme predates the guest checkout.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($this->injector);
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
        // CustomerGuestCheckoutTest for why that helper cannot be used here.
        $factory = new FixtureFactory($this->getPropelConnection());

        $admin = $factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    public function testSavingTheProductFormPersistsTheGuestCheckoutForbiddenFlag(): void
    {
        $this->loginAdmin();

        $factory = new FixtureFactory($this->getPropelConnection());
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();
        $product = $factory->product($category, $taxRule, $currency);

        self::assertSame(0, $product->getGuestCheckoutForbidden(), 'A freshly created product must default to allowing guest checkout.');

        // Pulled from the product list's create-product modal rather than the
        // edit page (see the class docblock): both forms share the same
        // csrf_token_id ('admin.product'), so the token is valid for either.
        $crawler = $this->client->request('GET', '/admin/products');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $token = $crawler->filter('[name="thelia_product_creation[_token]"]')->attr('value');
        self::assertNotNull($token, 'The product list must render a CSRF token this test can reuse.');

        $this->client->request('POST', '/admin/products/save', [
            'thelia_product_modification' => [
                'id' => (string) $product->getId(),
                'ref' => (string) $product->getRef(),
                'title' => 'Guest checkout forbidden probe',
                'default_category' => (string) $category->getId(),
                'locale' => 'en_US',
                'visible' => '1',
                'guest_checkout_forbidden' => '1',
                '_token' => $token,
            ],
        ]);

        self::assertSame(
            302,
            $this->client->getResponse()->getStatusCode(),
            'Saving a valid product form must redirect (any 200 here means the form was rejected).',
        );

        $reloaded = ProductQuery::create()->findPk($product->getId());
        self::assertNotNull($reloaded);
        self::assertSame(1, $reloaded->getGuestCheckoutForbidden());

        // Saving again without the checkbox field (unticked, so the browser
        // never submits it) must clear the flag back.
        $this->client->request('POST', '/admin/products/save', [
            'thelia_product_modification' => [
                'id' => (string) $product->getId(),
                'ref' => (string) $product->getRef(),
                'title' => 'Guest checkout forbidden probe',
                'default_category' => (string) $category->getId(),
                'locale' => 'en_US',
                'visible' => '1',
                '_token' => $token,
            ],
        ]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        $reloadedAgain = ProductQuery::create()->findPk($product->getId());
        self::assertNotNull($reloadedAgain);
        self::assertSame(0, $reloadedAgain->getGuestCheckoutForbidden());
    }
}
