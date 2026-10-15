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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The coupon types that target a list of products, categories or attribute
 * values: the editor posts the list as coupon_specific[<field>][], and every
 * selected id must reach the stored effects.
 */
final class CouponListFieldsTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        parent::setUp();

        $template = static::getContainer()->getParameter('thelia_admin_template');

        if ('default-twig' !== $template) {
            self::markTestSkipped(\sprintf(
                'The installed back-office template is "%s", not "default-twig".',
                \is_string($template) ? $template : 'unknown',
            ));
        }

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        // setUp() may have skipped before wiring the injector.
        if (isset($this->injector)) {
            $this->injector->clear();
        }
        parent::tearDown();
    }

    public function testEverySelectedProductIsSaved(): void
    {
        $factory = $this->loginAdmin();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();
        $first = $factory->product($category, $taxRule, $currency);
        $second = $factory->product($category, $taxRule, $currency);

        $effects = $this->createCoupon('thelia.coupon.type.remove_amount_on_products', [
            'amount' => '3',
            'category_id' => (string) $category->getId(),
            'products' => [(string) $first->getId(), (string) $second->getId()],
        ]);

        self::assertSame([$first->getId(), $second->getId()], $this->ids($effects['products'] ?? null));
    }

    public function testEverySelectedCategoryIsSaved(): void
    {
        $factory = $this->loginAdmin();
        $first = $factory->category();
        $second = $factory->category();

        $effects = $this->createCoupon('thelia.coupon.type.remove_amount_on_categories', [
            'amount' => '3',
            'categories' => [(string) $first->getId(), (string) $second->getId()],
        ]);

        self::assertSame([$first->getId(), $second->getId()], $this->ids($effects['categories'] ?? null));
    }

    public function testEverySelectedAttributeValueIsSaved(): void
    {
        $factory = $this->loginAdmin();
        $attribute = $factory->attribute();
        $first = $factory->attributeAv($attribute);
        $second = $factory->attributeAv($attribute);

        $effects = $this->createCoupon('thelia.coupon.type.remove_amount_on_attribute_av', [
            'amount' => '3',
            'attribute_id' => (string) $attribute->getId(),
            'attribute_avs' => [(string) $first->getId(), (string) $second->getId()],
        ]);

        self::assertSame([$first->getId(), $second->getId()], $this->ids($effects['attribute_avs'] ?? null));
    }

    private function loginAdmin(): FixtureFactory
    {
        // FixtureFactory built directly (not via createFixtureFactory()): see
        // CouponTriggerModeTest::loginAdmin() for why that helper cannot be used.
        $factory = new FixtureFactory($this->getPropelConnection());

        $admin = $factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);

        return $factory;
    }

    /**
     * @param array<string, mixed> $couponSpecific
     *
     * @return array<string, mixed> the effects stored for the new coupon
     */
    private function createCoupon(string $type, array $couponSpecific): array
    {
        $this->assertPageRenders('/admin/coupon/create');
        $token = $this->client->getCrawler()->filter('form[data-testid="coupon-edit-form"] input[name="_token"]');
        self::assertGreaterThan(0, $token->count(), 'The coupon editor must render a CSRF token this test can reuse.');

        $code = 'LIST-'.strtoupper(uniqid());

        $this->client->request('POST', '/admin/coupon/create', [
            '_token' => (string) $token->attr('value'),
            'locale' => 'en_US',
            'type' => $type,
            'trigger_mode' => Coupon::TRIGGER_MODE_CODE,
            'code' => $code,
            'title' => 'List coupon '.$code,
            'shortDescription' => '',
            'description' => '',
            'isEnabled' => '1',
            'expirationDate' => '',
            'is-unlimited' => '1',
            'perCustomerUsageCount' => '1',
            'coupon_specific' => $couponSpecific,
            'save_mode' => 'close',
        ]);

        self::assertSame(
            302,
            $this->client->getResponse()->getStatusCode(),
            'A complete coupon must be accepted (a 200 here means the fields were rejected).',
        );

        $created = CouponQuery::create()->filterByCode($code)->orderById(Criteria::DESC)->findOne();
        self::assertNotNull($created);

        return $created->getEffects();
    }

    /**
     * @return list<int>
     */
    private function ids(mixed $stored): array
    {
        return array_map('\intval', array_values((array) $stored));
    }
}
