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

namespace Thelia\Tests\Integration\Domain\Order;

use Symfony\Component\DependencyInjection\Container;
use Thelia\Domain\Order\Service\OrderTrackingUrlResolver;
use Thelia\Model\Module;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\Order;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Module\TrackingCarrierModule;

/**
 * The link an order gives to the carrier page following its parcel, read from the
 * delivery module of the order.
 */
final class OrderTrackingUrlResolverTest extends IntegrationTestCase
{
    private const string CARRIER_CODE = 'TrackingUrlTestCarrier';

    protected function tearDown(): void
    {
        // The module configuration is memoized in a static cache that outlives the
        // transaction rollback.
        ModuleConfigQuery::resetConfigCache();

        parent::tearDown();
    }

    public function testTheTemplateOfTheCarrierGivesTheLinkOfTheOrder(): void
    {
        $carrier = $this->carrier('https://carrier.example/track?parcel=%ID%');

        self::assertSame(
            'https://carrier.example/track?parcel=6A%2012',
            $this->resolver()->resolve($this->orderShippedBy($carrier, '6A 12')),
        );
    }

    public function testAnOrderWithoutTrackingNumberHasNoLink(): void
    {
        $carrier = $this->carrier('https://carrier.example/track?parcel=%ID%');

        self::assertNull($this->resolver()->resolve($this->orderShippedBy($carrier, null)));
    }

    public function testACarrierWithoutTrackingAddressGivesNoLinkAndNoError(): void
    {
        self::assertNull($this->resolver()->resolve($this->orderShippedBy($this->carrier(null), '6A12')));
    }

    /**
     * A template written straight into the database, past the back office form, is
     * checked again on the way out.
     */
    public function testAStoredTemplateThatIsNotAWebAddressGivesNoLink(): void
    {
        $carrier = $this->carrier('javascript:alert(1)//%ID%');

        self::assertNull($this->resolver()->resolve($this->orderShippedBy($carrier, '6A12')));
    }

    public function testAnOrderWhoseCarrierWasUninstalledKeepsItsNumberAndHasNoLink(): void
    {
        $order = $this->orderShippedBy($this->carrier('https://carrier.example/%ID%'), '6A12');
        $order->setDeliveryModuleId(null)->setDeliveryModuleTitle('Former carrier')->save($this->getPropelConnection());

        self::assertNull($this->resolver()->resolve($order));
        self::assertSame('6A12', $order->getDeliveryRef());
    }

    public function testACarrierModuleBuildingItsOwnLinkPrevailsOverTheTemplate(): void
    {
        $carrier = $this->carrier('https://carrier.example/%ID%');
        $resolver = $this->resolverWith(new TrackingCarrierModule(self::CARRIER_CODE, 'https://signed.example/{ref}?sig=abc'));

        self::assertSame('https://signed.example/6A12?sig=abc', $resolver->resolve($this->orderShippedBy($carrier, '6A12')));
        self::assertTrue($resolver->isProvidedByModule($carrier->getId()));
    }

    public function testALinkFromTheModuleThatIsNotAWebAddressIsDropped(): void
    {
        $carrier = $this->carrier(null);
        $resolver = $this->resolverWith(new TrackingCarrierModule(self::CARRIER_CODE, 'javascript:{ref}'));

        self::assertNull($resolver->resolve($this->orderShippedBy($carrier, '6A12')));
    }

    public function testACarrierModuleThatCannotTellGivesNoLink(): void
    {
        $carrier = $this->carrier('https://carrier.example/%ID%');
        $resolver = $this->resolverWith(new TrackingCarrierModule(self::CARRIER_CODE, null));

        self::assertNull($resolver->resolve($this->orderShippedBy($carrier, '6A12')));
    }

    /**
     * The module is third-party code: a failure in it costs the order its link, never
     * the order page, the API read or the status change that asked for the link.
     */
    public function testACarrierModuleThatFailsGivesNoLinkInsteadOfBreakingTheCaller(): void
    {
        $carrier = $this->carrier('https://carrier.example/%ID%');
        $resolver = $this->resolverWith(new TrackingCarrierModule(self::CARRIER_CODE, null, new \RuntimeException('Carrier API down')));

        self::assertNull($resolver->resolve($this->orderShippedBy($carrier, '6A12')));
    }

    public function testACarrierModuleThatCannotBeLoadedFallsBackToItsTemplate(): void
    {
        $carrier = $this->carrier('https://carrier.example/%ID%');
        $brokenContainer = new class extends Container {
            public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
            {
                throw new \RuntimeException('The module constructor failed');
            }
        };

        self::assertSame('https://carrier.example/6A12', (new OrderTrackingUrlResolver($brokenContainer))->resolve($this->orderShippedBy($carrier, '6A12')));
    }

    /**
     * The source of a carrier is read once per request: an order list does not read
     * the configuration once per order. The next request reads it again.
     */
    public function testTheCarrierIsReadOncePerRequestAndAgainAfterAReset(): void
    {
        $carrier = $this->carrier('https://carrier.example/first/%ID%');
        $resolver = $this->resolver();
        $order = $this->orderShippedBy($carrier, '6A12');
        self::assertSame('https://carrier.example/first/6A12', $resolver->resolve($order));

        ModuleConfigQuery::create()->setConfigValue($carrier->getId(), OrderTrackingUrlResolver::TRACKING_URL_CONFIG_KEY, 'https://carrier.example/second/%ID%');
        self::assertSame('https://carrier.example/first/6A12', $resolver->resolve($order), 'Same request: the carrier is not read again.');

        $resolver->reset();
        self::assertSame('https://carrier.example/second/6A12', $resolver->resolve($order));
    }

    private function resolver(): OrderTrackingUrlResolver
    {
        // The carrier row has no service: the container answers as for a module that
        // is installed but not active, and the template applies.
        return $this->resolverWith(null);
    }

    private function resolverWith(?TrackingCarrierModule $module): OrderTrackingUrlResolver
    {
        $container = new Container();

        if (null !== $module) {
            $container->set('module.'.self::CARRIER_CODE, $module);
        }

        return new OrderTrackingUrlResolver($container);
    }

    private function carrier(?string $trackingUrlTemplate): Module
    {
        $module = new Module();
        $module
            ->setCode(self::CARRIER_CODE)
            ->setType(BaseModule::DELIVERY_MODULE_TYPE)
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->setFullNamespace(self::CARRIER_CODE.'\\'.self::CARRIER_CODE)
            ->save($this->getPropelConnection());

        if (null !== $trackingUrlTemplate) {
            ModuleConfigQuery::create()->setConfigValue($module->getId(), OrderTrackingUrlResolver::TRACKING_URL_CONFIG_KEY, $trackingUrlTemplate);
        }

        return $module;
    }

    private function orderShippedBy(Module $carrier, ?string $trackingNumber): Order
    {
        $order = $this->createFixtureFactory()->order(null, ['deliveryModuleCode' => $carrier->getCode()]);
        $order->setDeliveryRef($trackingNumber)->save($this->getPropelConnection());

        return $order;
    }
}
