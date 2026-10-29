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

namespace Thelia\Tests\Api\Front;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\CartItem;
use Thelia\Model\ConsentQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\Currency;
use Thelia\Model\Map\ConsentTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * A pack the catalogue changed under a cart, placed through the front API: refused in the
 * shape of every refusal of the placement, with a code of its own and the pack named, and no
 * order written. Whether the change came before the placement (the step of the cart reports
 * it) or while it ran (the order refuses it), the caller reads the same answer.
 */
final class CheckoutBundleApiTest extends ApiTestCase
{
    use BuildsBundles;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        ConsentTableMap::clearInstancePool();

        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->getPropelConnection()) as $consent) {
            $consent->setActive(0)->save($this->getPropelConnection());
        }

        $this->getService(ConsentProvider::class)->forgetCache();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->getService(EventDispatcherInterface::class)->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        $this->getService(ConsentProvider::class)->forgetCache();

        parent::tearDown();
    }

    public function testAPackWhoseComponentWentOfflineIsRefusedNamingThePack(): void
    {
        $checkout = $this->readyCheckoutHoldingAPack();
        $checkout['helmet']->setVisible(0)->save($this->getPropelConnection());

        $response = $this->jsonRequest('POST', $this->url($checkout['cartId'], 'place'), token: $checkout['token']);

        $this->assertThePackIsRefused($response, $checkout);
    }

    public function testAPackChangedWhileTheOrderIsPlacedIsRefusedTheSameWay(): void
    {
        $checkout = $this->readyCheckoutHoldingAPack();

        // After the validation of the placement, before the order is written.
        $this->listen(TheliaEvents::ORDER_PAY, function () use ($checkout): void {
            $checkout['helmet']->setVisible(0)->save($this->getPropelConnection());
        }, 1024);

        $response = $this->jsonRequest('POST', $this->url($checkout['cartId'], 'place'), token: $checkout['token']);

        $this->assertThePackIsRefused($response, $checkout);
    }

    /**
     * @param array{cartId: int, bundle: Product, bike: Product} $checkout
     */
    private function assertThePackIsRefused(\Symfony\Component\HttpFoundation\Response $response, array $checkout): void
    {
        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        $refusal = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertFalse($refusal['ready']);
        $violations = array_values(array_filter($refusal['violations'], static fn (array $violation): bool => CheckoutViolationCode::BundleLineNotOrderable->value === $violation['code']));
        self::assertCount(1, $violations, (string) $response->getContent());
        self::assertSame('cart', $violations[0]['stepCode']);
        self::assertSame((string) $checkout['bundle']->getRef(), $violations[0]['details']['productReference']);
        self::assertStringContainsString((string) $checkout['bundle']->getRef(), $violations[0]['message']);

        self::assertCount(0, OrderQuery::create()->filterByCartId($checkout['cartId'])->find($this->getPropelConnection()));
        self::assertSame(7.0, (float) ProductSaleElementsQuery::create()->filterByProductId($checkout['bike']->getId())->findOne($this->getPropelConnection())?->getQuantity());
    }

    /**
     * @return array{cartId: int, token: string, bundle: Product, bike: Product, helmet: Product}
     */
    private function readyCheckoutHoldingAPack(): array
    {
        $factory = $this->createFixtureFactory();
        $country = $factory->country();
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery') ?? throw new \RuntimeException('No delivery module.');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque') ?? throw new \RuntimeException('No Cheque module.');

        $area = (new Area())->setName('Checkout bundle API test area '.uniqid());
        $area->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($this->getPropelConnection());
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($deliveryModule->getId())->save($this->getPropelConnection());

        $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
            $event->setValidModule(true);
            $event->setPostage(new OrderPostage(7.5, 1.5, 'VAT'));
            $event->stopPropagation();
        });
        $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
            $event->setValidModule(true);
            $event->stopPropagation();
        });

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $address = $factory->address($customer, $country);

        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($factory, $service);

        $cart = $factory->cart($customer, ['currency' => Currency::getDefaultCurrency()]);
        (new CartItem())
            ->setCartId($cart->getId())
            ->setProductId($example['bundle']->getId())
            ->setProductSaleElementsId($this->saleElementsOf($example['bundle'])->getId())
            ->setQuantity(1)
            ->setPrice('600.000000')
            ->setPromoPrice('540.000000')
            ->setPromo(1)
            ->setIsOffered(0)
            ->setBundleComposition(BundleComposition::fromArray([
                ['bundleItemId' => (int) $example['bike']->getId(), 'quantity' => '1'],
                ['bundleItemId' => (int) $example['helmet']->getId(), 'quantity' => '2'],
            ])->toCanonicalJson())
            ->save($this->getPropelConnection());

        $token = $this->authenticateAsCustomer($customer);
        $cartId = (int) $cart->getId();

        foreach ([
            'delivery_address' => ['addressId' => $address->getId()],
            'invoice_address' => ['addressId' => $address->getId()],
            'delivery_module' => ['deliveryModuleId' => $deliveryModule->getId()],
            'payment_module' => ['paymentModuleId' => $paymentModule->getId()],
        ] as $step => $body) {
            self::assertJsonResponseSuccessful($this->jsonRequest('POST', $this->url($cartId, $step), $body, token: $token));
        }

        $validation = $this->jsonRequest('GET', $this->url($cartId, 'validation'), token: $token);
        self::assertSame(['ready' => true, 'violations' => []], json_decode((string) $validation->getContent(), true), 'The pack is valid when the checkout is ready.');

        return [
            'cartId' => $cartId,
            'token' => $token,
            'bundle' => $example['bundle'],
            'bike' => $example['bikeProduct'],
            'helmet' => $example['helmetProduct'],
        ];
    }

    private function url(int $cartId, string $step): string
    {
        return '/api/front/account/checkout/'.$cartId.'/'.$step;
    }

    private function listen(string $eventName, callable $listener, int $priority = 512): void
    {
        $this->getService(EventDispatcherInterface::class)->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }
}
