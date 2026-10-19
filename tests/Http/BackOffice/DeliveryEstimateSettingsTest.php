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
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryTimeEstimator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\DeliveryDateRuleQuery;
use Thelia\Model\Module;
use Thelia\Model\ProductQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;
use Thelia\Tests\Support\Delivery\RegistersDeliveryDateTestCarrier;

/**
 * The merchant's side of the estimated delivery dates: the delays of a carrier and the
 * cutoff of the shop on the delivery dates screens, the availability delay of a product,
 * and the interval an order was announced with on its page.
 *
 * The screens belong to the back-office theme, which ships as its own package: a theme
 * older than the estimate is reported as skipped rather than failed.
 */
final class DeliveryEstimateSettingsTest extends WebIntegrationTestCase
{
    use RegistersDeliveryDateTestCarrier;

    /**
     * Written as a string on purpose: `::class` on a missing import resolves silently in
     * the current namespace, and the guard would then skip the whole file for ever.
     */
    private const CONTROLLER = 'BackOfficeDefaultTwigBundle\\Controller\\Configuration\\DeliveryDateController';

    private AdminSessionInjector $injector;

    private Module $carrier;

    private string $cutoffBefore;

    protected function setUp(): void
    {
        if (!method_exists(self::CONTROLLER, 'saveDelays')) {
            self::markTestSkipped('The installed back-office theme does not set delivery estimates.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
        $admin = (new FixtureFactory($this->getPropelConnection()))->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);

        $this->carrier = $this->registerDeliveryDateTestCarrier(static::getContainer(), $this->getPropelConnection());
        $this->cutoffBefore = (string) ConfigQuery::read(DeliveryTimeEstimator::CUTOFF_CONFIG_NAME, '');
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
            ConfigQuery::write(DeliveryTimeEstimator::CUTOFF_CONFIG_NAME, $this->cutoffBefore);
        }

        parent::tearDown();
    }

    public function testTheDelaysOfACarrierAreSavedAndTakenOff(): void
    {
        $page = $this->client->request('GET', '/admin/configuration/delivery-dates/carrier/'.$this->carrier->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $form = $page->filter('[data-testid="delivery-date-estimate-form"]');
        self::assertCount(1, $form, 'The carrier page carries the estimate form.');
        $token = (string) $form->filter('[name="_token"]')->attr('value');

        $this->saveDelays($token, '1', '2', '4');
        $rule = DeliveryDateRuleQuery::create()->findOneByModuleId($this->carrier->getId());
        self::assertNotNull($rule);
        self::assertSame([1, 2, 4], [$rule->getPreparationDays(), $rule->getTransitMinDays(), $rule->getTransitMaxDays()]);

        $list = $this->client->request('GET', '/admin/configuration/delivery-dates');
        self::assertStringContainsString('1 + 2 to 4', $list->filter('[data-testid="delivery-date-carrier-estimate-'.$this->carrier->getCode().'"]')->text());

        $this->saveDelays($token, '', '', '');
        $rule = DeliveryDateRuleQuery::create()->findOneByModuleId($this->carrier->getId());
        self::assertNotNull($rule);
        self::assertSame([null, null, null], [$rule->getPreparationDays(), $rule->getTransitMinDays(), $rule->getTransitMaxDays()]);
    }

    public function testInconsistentDelaysAreRefusedAndNothingIsSaved(): void
    {
        $page = $this->client->request('GET', '/admin/configuration/delivery-dates/carrier/'.$this->carrier->getId());
        $token = (string) $page->filter('[data-testid="delivery-date-estimate-form"] [name="_token"]')->attr('value');

        $this->saveDelays($token, '1', '5', '2');

        $rule = DeliveryDateRuleQuery::create()->findOneByModuleId($this->carrier->getId());
        self::assertTrue(null === $rule || null === $rule->getTransitMinDays());
    }

    /**
     * A delay typed by hand that is not a number of days is refused, not read as zero.
     */
    public function testADelayThatIsNotANumberOfDaysIsRefused(): void
    {
        $page = $this->client->request('GET', '/admin/configuration/delivery-dates/carrier/'.$this->carrier->getId());
        $token = (string) $page->filter('[data-testid="delivery-date-estimate-form"] [name="_token"]')->attr('value');

        $this->saveDelays($token, '1', 'two', '3');

        // Nothing of the form is written, the delays typed right included.
        $rule = DeliveryDateRuleQuery::create()->findOneByModuleId($this->carrier->getId());
        self::assertTrue(null === $rule || [null, null, null] === [$rule->getPreparationDays(), $rule->getTransitMinDays(), $rule->getTransitMaxDays()]);
    }

    /**
     * A refusal sends the merchant back to what they typed, not to what was stored before.
     */
    public function testRefusedDelaysAreTypedBackIntoTheForm(): void
    {
        $page = $this->client->request('GET', '/admin/configuration/delivery-dates/carrier/'.$this->carrier->getId());
        $token = (string) $page->filter('[data-testid="delivery-date-estimate-form"] [name="_token"]')->attr('value');

        $this->saveDelays($token, '1', '5', '2');

        $form = $this->client->request('GET', '/admin/configuration/delivery-dates/carrier/'.$this->carrier->getId())->filter('[data-testid="delivery-date-estimate-form"]');
        self::assertSame(['1', '5', '2'], [
            $form->filter('[name="preparation_days"]')->attr('value'),
            $form->filter('[name="transit_min_days"]')->attr('value'),
            $form->filter('[name="transit_max_days"]')->attr('value'),
        ]);

        // Once shown, the typed values are gone: the next visit reads what is stored.
        $again = $this->client->request('GET', '/admin/configuration/delivery-dates/carrier/'.$this->carrier->getId());
        self::assertSame('', (string) $again->filter('[data-testid="delivery-date-estimate-form"] [name="transit_min_days"]')->attr('value'));
    }

    public function testTheCutoffOfTheShopIsSavedWithItsClosedDays(): void
    {
        $page = $this->client->request('GET', '/admin/configuration/delivery-dates');
        $form = $page->filter('[data-testid="delivery-date-shop-form"]');
        self::assertCount(1, $form->filter('[data-testid="delivery-date-cutoff"]'));

        $this->client->request('POST', '/admin/configuration/delivery-dates/shop/save', [
            '_token' => (string) $form->filter('[name="_token"]')->attr('value'),
            'closed_weekdays' => ['7'],
            'cutoff_time' => '14:30',
        ]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame('14:30', ConfigQuery::read(DeliveryTimeEstimator::CUTOFF_CONFIG_NAME));
    }

    public function testARefusedCutoffLeavesTheClosedDaysAsTheyWere(): void
    {
        $closedBefore = (string) ConfigQuery::read('delivery_closed_weekdays', '');
        $page = $this->client->request('GET', '/admin/configuration/delivery-dates');
        $token = (string) $page->filter('[data-testid="delivery-date-shop-form"] [name="_token"]')->attr('value');

        try {
            $this->client->request('POST', '/admin/configuration/delivery-dates/shop/save', [
                '_token' => $token,
                'closed_weekdays' => ['1', '2'],
                'cutoff_time' => '2pm',
            ]);

            self::assertSame(302, $this->client->getResponse()->getStatusCode());
            ConfigQuery::resetCache();
            self::assertSame($closedBefore, (string) ConfigQuery::read('delivery_closed_weekdays', ''));

            // The form shows what the merchant typed, the refused hour and the days with it.
            $form = $this->client->request('GET', '/admin/configuration/delivery-dates')->filter('[data-testid="delivery-date-shop-form"]');
            self::assertSame('2pm', $form->filter('[name="cutoff_time"]')->attr('value'));
            self::assertSame(['1', '2'], $form->filter('[name="closed_weekdays[]"]:checked')->each(static fn ($box): string => (string) $box->attr('value')));
        } finally {
            ConfigQuery::write('delivery_closed_weekdays', $closedBefore);
        }
    }

    public function testTheAvailabilityDelayOfAProductIsSavedAndCleared(): void
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $category = $factory->category();
        $product = $factory->product($category, $factory->taxRule(), $factory->currency());

        $this->assertPageRenders('/admin/products');
        $token = (string) $this->client->getCrawler()->filter('[name="thelia_product_creation[_token]"]')->attr('value');

        foreach (['4' => 4, '' => null] as $typed => $stored) {
            $this->client->request('POST', '/admin/products/save', [
                'thelia_product_modification' => [
                    'id' => (string) $product->getId(),
                    'ref' => (string) $product->getRef(),
                    'title' => 'Availability delay probe',
                    'default_category' => (string) $category->getId(),
                    'locale' => 'en_US',
                    'visible' => '1',
                    'availability_delay_days' => (string) $typed,
                    '_token' => $token,
                ],
            ]);

            self::assertSame(302, $this->client->getResponse()->getStatusCode());
            $reloaded = ProductQuery::create()->findPk($product->getId());
            self::assertNotNull($reloaded);
            $reloaded->reload();
            self::assertSame($stored, $reloaded->getAvailabilityDelayDays());
        }
    }

    public function testTheOrderPageShowsTheIntervalTheCustomerWasAnnounced(): void
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $order = $factory->order($factory->customer($factory->customerTitle()));
        $order->setEstimatedDeliveryFrom('2026-09-03')->setEstimatedDeliveryTo('2026-09-07')->save($this->getPropelConnection());

        $page = $this->client->request('GET', '/admin/order/update/'.$order->getId());

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $line = $page->filter('[data-testid="order-estimated-delivery"]');
        self::assertCount(1, $line);
        self::assertStringContainsString('September 3', $line->text());
        self::assertStringContainsString('September 7', $line->text());
    }

    private function saveDelays(string $token, string $preparation, string $min, string $max): void
    {
        $this->client->request('POST', '/admin/configuration/delivery-dates/carrier/'.$this->carrier->getId().'/delays/save', [
            '_token' => $token,
            'preparation_days' => $preparation,
            'transit_min_days' => $min,
            'transit_max_days' => $max,
        ]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
    }
}
