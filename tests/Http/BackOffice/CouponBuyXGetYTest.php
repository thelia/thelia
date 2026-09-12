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
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Promotion\Coupon\Type\BuyXGetY;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Back-office volet of the "Buy X, get Y" coupon type: the inputs fragment the
 * theme renders for it (BackOfficeDefaultTwigBundle\Service\Coupon\CouponInputsRenderer)
 * and the round-trip of its coupon_specific fields through
 * Thelia\Domain\Promotion\Coupon\Type\BuyXGetY::getEffects().
 */
final class CouponBuyXGetYTest extends WebIntegrationTestCase
{
    private const COUPON_TYPE = 'thelia.coupon.type.buy_x_get_y';

    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever back-office
        // theme it is given, and one that predates the automatic promotions has
        // no "Buy X, get Y" fragment to render.
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
     * whether or not it is the template the store actually runs: the screens
     * asserted here only exist when it is the active one.
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

    private function couponFormToken(string $url): string
    {
        $this->assertPageRenders($url);

        $token = $this->client->getCrawler()->filter('form[data-testid="coupon-edit-form"] input[name="_token"]');
        self::assertGreaterThan(0, $token->count(), 'The coupon editor must render a CSRF token this test can reuse.');

        return (string) $token->attr('value');
    }

    public function testACompleteBuyXGetYPromotionIsCreatedAndReadBackByTheEditor(): void
    {
        $this->loginAdmin();

        $factory = new FixtureFactory($this->getPropelConnection());
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();
        $trigger = $factory->product($category, $taxRule, $currency, ['title' => 'Triggering product '.uniqid()]);
        $offered = $factory->product($category, $taxRule, $currency, ['title' => 'Offered product '.uniqid()]);

        $token = $this->couponFormToken('/admin/coupon/create');
        $title = 'Three bought, one offered '.uniqid();

        $this->client->request('POST', '/admin/coupon/create', [
            '_token' => $token,
            'locale' => 'en_US',
            'type' => self::COUPON_TYPE,
            'trigger_mode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'code' => '',
            'title' => $title,
            'shortDescription' => '',
            'description' => '',
            'isEnabled' => '1',
            'expirationDate' => '',
            'is-unlimited' => '1',
            'perCustomerUsageCount' => '1',
            'coupon_specific' => [
                BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_SELECTION,
                BuyXGetY::TRIGGER_IDS_FIELD => [(string) $trigger->getId()],
                BuyXGetY::TRIGGER_QUANTITY_FIELD => '3',
                BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_PRODUCT,
                BuyXGetY::TARGET_PRODUCT_ID_FIELD => (string) $offered->getId(),
                BuyXGetY::OFFERED_QUANTITY_FIELD => '1',
                BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_PERCENTAGE,
                BuyXGetY::DISCOUNT_VALUE_FIELD => '50',
            ],
            'save_mode' => 'close',
        ]);

        self::assertSame(
            302,
            $this->client->getResponse()->getStatusCode(),
            'A complete "Buy X, get Y" promotion must be accepted (a 200 here means the fields were rejected).',
        );

        $created = CouponQuery::create()->orderById(Criteria::DESC)->findOne();
        self::assertNotNull($created);
        self::assertSame(self::COUPON_TYPE, $created->getType());
        self::assertSame(Coupon::TRIGGER_MODE_AUTOMATIC, $created->getTriggerMode());

        $effects = $created->getEffects();
        self::assertSame(BuyXGetY::TRIGGER_SCOPE_SELECTION, $effects[BuyXGetY::TRIGGER_SCOPE_FIELD]);
        self::assertSame([(int) $trigger->getId()], $effects[BuyXGetY::TRIGGER_IDS_FIELD]);
        self::assertSame(3, $effects[BuyXGetY::TRIGGER_QUANTITY_FIELD]);
        self::assertSame(BuyXGetY::TARGET_MODE_PRODUCT, $effects[BuyXGetY::TARGET_MODE_FIELD]);
        self::assertSame((int) $offered->getId(), $effects[BuyXGetY::TARGET_PRODUCT_ID_FIELD]);
        self::assertSame(1, $effects[BuyXGetY::OFFERED_QUANTITY_FIELD]);
        self::assertSame(BuyXGetY::DISCOUNT_TYPE_PERCENTAGE, $effects[BuyXGetY::DISCOUNT_TYPE_FIELD]);
        self::assertSame(50.0, (float) $effects[BuyXGetY::DISCOUNT_VALUE_FIELD]);

        // Reopening the coupon must show the setup back, not an empty fragment.
        $this->assertPageRenders('/admin/coupon/update/'.$created->getId());
        $crawler = $this->client->getCrawler();

        self::assertGreaterThan(
            0,
            $crawler->filter('[data-testid="coupon-buy-x-get-y"]')->count(),
            'The editor must render the "Buy X, get Y" fragment for a coupon of that type.',
        );
        self::assertGreaterThan(
            0,
            $crawler->filter('[data-testid="coupon-buy-x-get-y-scope-selection"][checked]')->count(),
            'The saved triggering scope must come back selected.',
        );
        self::assertSame(
            '3',
            $crawler->filter('[data-testid="coupon-buy-x-get-y-trigger-quantity"]')->attr('value'),
            'The saved triggering quantity must come back in the form.',
        );
        self::assertGreaterThan(
            0,
            $crawler->filter('[data-testid="coupon-buy-x-get-y-target-product"] option[selected]')->count(),
            'The saved offered product must come back selected.',
        );
        self::assertSame(
            '50',
            $crawler->filter('[data-testid="coupon-buy-x-get-y-discount-value"]')->attr('value'),
            'The saved discount value must come back in the form.',
        );
    }

    public function testAnIncompleteBuyXGetYPromotionIsRefused(): void
    {
        $this->loginAdmin();

        $before = (int) CouponQuery::create()->count();
        $token = $this->couponFormToken('/admin/coupon/create');

        $this->client->request('POST', '/admin/coupon/create', [
            '_token' => $token,
            'locale' => 'en_US',
            'type' => self::COUPON_TYPE,
            'trigger_mode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'code' => '',
            'title' => 'Offer with nothing to trigger it '.uniqid(),
            'isEnabled' => '1',
            'expirationDate' => '',
            'is-unlimited' => '1',
            'perCustomerUsageCount' => '1',
            'coupon_specific' => [
                BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_SELECTION,
                BuyXGetY::TRIGGER_IDS_FIELD => [],
                BuyXGetY::TRIGGER_QUANTITY_FIELD => '3',
                BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_SAME,
                BuyXGetY::OFFERED_QUANTITY_FIELD => '1',
                BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_FREE,
                BuyXGetY::DISCOUNT_VALUE_FIELD => '',
            ],
            'save_mode' => 'close',
        ]);

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'An offer with no triggering product must come back to the form, not redirect.',
        );
        self::assertSame($before, (int) CouponQuery::create()->count(), 'Nothing must have been persisted.');
    }

    /**
     * A refused save must not cost the merchant their work: the form comes back
     * with the type they chose and every value they typed in its fragment, not
     * with an empty screen or with whatever the database still holds.
     */
    public function testARefusedSaveComesBackWithThePostedBuyXGetYFragment(): void
    {
        $this->loginAdmin();

        $factory = new FixtureFactory($this->getPropelConnection());
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();
        $trigger = $factory->product($category, $taxRule, $currency, ['title' => 'Triggering product '.uniqid()]);

        $before = (int) CouponQuery::create()->count();
        $token = $this->couponFormToken('/admin/coupon/create');
        $title = 'Percentage over a hundred '.uniqid();

        // 150 % off: BuyXGetY::getEffects() refuses it, and the whole fragment
        // must come back as posted so the merchant only fixes that one field.
        $crawler = $this->client->request('POST', '/admin/coupon/create', [
            '_token' => $token,
            'locale' => 'en_US',
            'type' => self::COUPON_TYPE,
            'trigger_mode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'code' => '',
            'title' => $title,
            'shortDescription' => 'Shown in the cart',
            'description' => 'The long story',
            'isEnabled' => '1',
            'expirationDate' => '',
            'is-unlimited' => '1',
            'perCustomerUsageCount' => '1',
            'coupon_specific' => [
                BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_SELECTION,
                BuyXGetY::TRIGGER_IDS_FIELD => [(string) $trigger->getId()],
                BuyXGetY::TRIGGER_QUANTITY_FIELD => '4',
                BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_CHEAPEST,
                BuyXGetY::OFFERED_QUANTITY_FIELD => '2',
                BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_PERCENTAGE,
                BuyXGetY::DISCOUNT_VALUE_FIELD => '150',
            ],
            'save_mode' => 'close',
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame($before, (int) CouponQuery::create()->count(), 'Nothing must have been persisted.');

        self::assertSame(
            self::COUPON_TYPE,
            $crawler->filter('select[name="type"] option[selected]')->attr('value'),
            'The refused form must come back on the coupon type that was posted.',
        );
        self::assertGreaterThan(
            0,
            $crawler->filter('[data-testid="coupon-buy-x-get-y"]')->count(),
            'The fragment of the posted type must be re-rendered, not dropped.',
        );

        self::assertGreaterThan(
            0,
            $crawler->filter('[data-testid="coupon-buy-x-get-y-scope-selection"][checked]')->count(),
            'The posted triggering scope must come back selected.',
        );
        self::assertSame(
            (string) $trigger->getId(),
            $crawler->filter('#buy-x-get-y-trigger-products option[selected]')->attr('value'),
            'The posted triggering product must come back selected.',
        );
        self::assertSame(
            '4',
            $crawler->filter('[data-testid="coupon-buy-x-get-y-trigger-quantity"]')->attr('value'),
            'The posted triggering quantity must come back in the form.',
        );
        self::assertGreaterThan(
            0,
            $crawler->filter('[data-testid="coupon-buy-x-get-y-target-cheapest"][checked]')->count(),
            'The posted target mode must come back selected.',
        );
        self::assertSame(
            '2',
            $crawler->filter('[data-testid="coupon-buy-x-get-y-offered-quantity"]')->attr('value'),
            'The posted offered quantity must come back in the form.',
        );
        self::assertGreaterThan(
            0,
            $crawler->filter('[data-testid="coupon-buy-x-get-y-discount-percentage"][checked]')->count(),
            'The posted discount type must come back selected.',
        );
        self::assertSame(
            '150',
            $crawler->filter('[data-testid="coupon-buy-x-get-y-discount-value"]')->attr('value'),
            'The refused value itself must come back: it is the one field the merchant has to fix.',
        );

        self::assertSame($title, $crawler->filter('#coupon-title')->attr('value'));
        self::assertGreaterThan(
            0,
            $crawler->filter('[data-testid="coupon-trigger-mode-automatic"][checked]')->count(),
            'The posted trigger mode must come back selected.',
        );
    }
}
