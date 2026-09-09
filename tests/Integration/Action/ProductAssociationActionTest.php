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

namespace Thelia\Tests\Integration\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Product\ProductAddAccessoryEvent;
use Thelia\Core\Event\Product\ProductAddAssociationEvent;
use Thelia\Core\Event\Product\ProductDeleteAssociationEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Domain\Catalog\Product\Exception\ProductAssociationTypeNotFoundException;
use Thelia\Domain\Catalog\Product\Exception\SelfAssociationException;
use Thelia\Domain\Catalog\Product\ProductFacade;
use Thelia\Model\Accessory;
use Thelia\Model\AccessoryQuery;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductAssociationType;
use Thelia\Model\TaxRule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class ProductAssociationActionTest extends IntegrationTestCase
{
    private EventDispatcherInterface $dispatcher;
    private FixtureFactory $factory;
    private Category $category;
    private Currency $currency;
    private TaxRule $taxRule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->getService(EventDispatcherInterface::class);
        $this->factory = $this->createFixtureFactory();
        $this->category = $this->factory->category();
        $this->currency = $this->factory->currency();
        $this->taxRule = $this->factory->taxRule();
    }

    public function testAddAssociationWritesTheRelationUnderItsType(): void
    {
        $product = $this->product();
        $upSell = $this->product();

        $this->add($product, $upSell, ProductAssociationType::CODE_UP_SELLING);

        $relation = $this->relation($product, $upSell, ProductAssociationType::CODE_UP_SELLING);
        self::assertNotNull($relation);
        self::assertSame(
            ProductAssociationType::CODE_UP_SELLING,
            $relation->getProductAssociationType()->getCode(),
        );
    }

    public function testPositionIsCountedWithinEachType(): void
    {
        $product = $this->product();

        $this->add($product, $this->product(), ProductAssociationType::CODE_ACCESSORY);
        $this->add($product, $this->product(), ProductAssociationType::CODE_ACCESSORY);
        $firstUpSell = $this->product();
        $this->add($product, $firstUpSell, ProductAssociationType::CODE_UP_SELLING);

        $relation = $this->relation($product, $firstUpSell, ProductAssociationType::CODE_UP_SELLING);

        self::assertSame(1, $relation->getPosition(), 'A type numbers its own relations from one');
    }

    public function testUpdatePositionMovesTheRelationWithinItsTypeOnly(): void
    {
        $product = $this->product();
        $firstAccessory = $this->product();
        $secondAccessory = $this->product();
        $upSell = $this->product();

        $this->add($product, $firstAccessory, ProductAssociationType::CODE_ACCESSORY);
        $this->add($product, $secondAccessory, ProductAssociationType::CODE_ACCESSORY);
        $this->add($product, $upSell, ProductAssociationType::CODE_UP_SELLING);

        $second = $this->relation($product, $secondAccessory, ProductAssociationType::CODE_ACCESSORY);

        $this->dispatcher->dispatch(
            new UpdatePositionEvent($second->getId(), UpdatePositionEvent::POSITION_UP),
            TheliaEvents::PRODUCT_UPDATE_ASSOCIATION_POSITION,
        );

        self::assertSame(1, $this->relation($product, $secondAccessory, ProductAssociationType::CODE_ACCESSORY)->getPosition());
        self::assertSame(2, $this->relation($product, $firstAccessory, ProductAssociationType::CODE_ACCESSORY)->getPosition());
        self::assertSame(
            1,
            $this->relation($product, $upSell, ProductAssociationType::CODE_UP_SELLING)->getPosition(),
            'Reordering a block leaves the other blocks of the product where they were',
        );
    }

    public function testReciprocalTypeWritesTheMirrorRelation(): void
    {
        $product = $this->product();
        $complementary = $this->product();

        $this->add($product, $complementary, ProductAssociationType::CODE_CROSS_SELLING);

        self::assertNotNull($this->relation($product, $complementary, ProductAssociationType::CODE_CROSS_SELLING));
        self::assertNotNull(
            $this->relation($complementary, $product, ProductAssociationType::CODE_CROSS_SELLING),
            'A reciprocal type relates the other product back',
        );
    }

    public function testReciprocalTypeAnnouncesEachDirectionOnce(): void
    {
        $product = $this->product();
        $complementary = $this->product();

        $announced = [];
        $listener = static function (ProductAddAssociationEvent $event) use (&$announced): void {
            $announced[] = $event->getProduct()->getId().'-'.$event->getAssociatedProductId();
        };

        $this->listenTo(TheliaEvents::PRODUCT_ADD_ASSOCIATION, $listener, function () use ($product, $complementary): void {
            $this->add($product, $complementary, ProductAssociationType::CODE_CROSS_SELLING);
        });

        sort($announced);
        $expected = [
            $product->getId().'-'.$complementary->getId(),
            $complementary->getId().'-'.$product->getId(),
        ];
        sort($expected);

        self::assertSame(
            $expected,
            $announced,
            'Each direction is announced exactly once, so a subscriber cannot count the same relation twice',
        );
    }

    public function testNonReciprocalTypeLeavesTheOtherProductAlone(): void
    {
        $product = $this->product();
        $accessory = $this->product();

        $this->add($product, $accessory, ProductAssociationType::CODE_ACCESSORY);

        self::assertNull($this->relation($accessory, $product, ProductAssociationType::CODE_ACCESSORY));
    }

    public function testRemoveAssociationTakesBothDirectionsOfAReciprocalType(): void
    {
        $product = $this->product();
        $complementary = $this->product();

        $this->add($product, $complementary, ProductAssociationType::CODE_CROSS_SELLING);

        $this->dispatcher->dispatch(
            new ProductDeleteAssociationEvent($product, $complementary->getId(), ProductAssociationType::CODE_CROSS_SELLING),
            TheliaEvents::PRODUCT_REMOVE_ASSOCIATION,
        );

        self::assertNull($this->relation($product, $complementary, ProductAssociationType::CODE_CROSS_SELLING));
        self::assertNull($this->relation($complementary, $product, ProductAssociationType::CODE_CROSS_SELLING));
    }

    public function testAssociatingAProductWithItselfIsRefused(): void
    {
        $product = $this->product();

        $this->expectException(SelfAssociationException::class);

        try {
            $this->add($product, $product, ProductAssociationType::CODE_ACCESSORY);
        } finally {
            self::assertSame(
                0,
                AccessoryQuery::create()->filterByProductId($product->getId())->count(),
                'A refused relation writes nothing',
            );
        }
    }

    public function testAddAccessoryStillWritesTheAccessoryType(): void
    {
        $product = $this->product();
        $accessory = $this->product();

        $this->facade()->addAccessory($product->getId(), $accessory->getId());

        $relation = $this->relation($product, $accessory, ProductAssociationType::CODE_ACCESSORY);
        self::assertNotNull($relation, 'The accessory path keeps writing under the type it always meant');
    }

    public function testAddAssociationOfTheAccessoryTypeAnnouncesTheAccessoryEventOnce(): void
    {
        $product = $this->product();
        $accessory = $this->product();

        $announced = 0;
        $listener = static function () use (&$announced): void {
            ++$announced;
        };

        $this->listenTo(TheliaEvents::PRODUCT_ADD_ACCESSORY, $listener, function () use ($product, $accessory): void {
            $this->add($product, $accessory, ProductAssociationType::CODE_ACCESSORY);
        });

        self::assertSame(1, $announced, 'A subscriber of the accessory event still hears an accessory being related');
        self::assertSame(1, AccessoryQuery::create()->filterByProductId($product->getId())->count());
    }

    public function testTheAccessoryEventAnnouncesTheAssociationOnceAndWritesOneRelation(): void
    {
        $product = $this->product();
        $accessory = $this->product();

        $announced = 0;
        $listener = static function () use (&$announced): void {
            ++$announced;
        };

        $this->listenTo(TheliaEvents::PRODUCT_ADD_ASSOCIATION, $listener, function () use ($product, $accessory): void {
            $this->dispatcher->dispatch(
                new ProductAddAccessoryEvent($product, $accessory->getId()),
                TheliaEvents::PRODUCT_ADD_ACCESSORY,
            );
        });

        self::assertSame(1, $announced, 'The accessory path routes through the association path, once');
        self::assertSame(1, AccessoryQuery::create()->filterByProductId($product->getId())->count());
        self::assertNotNull($this->relation($product, $accessory, ProductAssociationType::CODE_ACCESSORY));
    }

    public function testGetAssociationsFiltersByType(): void
    {
        $product = $this->product();
        $accessory = $this->product();
        $upSell = $this->product();

        $this->add($product, $accessory, ProductAssociationType::CODE_ACCESSORY);
        $this->add($product, $upSell, ProductAssociationType::CODE_UP_SELLING);

        $everything = $this->facade()->getAssociations($product->getId());
        self::assertCount(2, $everything);

        $upSells = $this->facade()->getAssociations($product->getId(), ProductAssociationType::CODE_UP_SELLING);
        self::assertCount(1, $upSells);
        self::assertSame($upSell->getId(), $upSells[0]->getAccessory());
    }

    public function testFacadeAddsAndRemovesAnAssociation(): void
    {
        $product = $this->product();
        $complementary = $this->product();

        $this->facade()->addAssociation($product->getId(), $complementary->getId(), ProductAssociationType::CODE_CROSS_SELLING);

        self::assertNotNull($this->relation($product, $complementary, ProductAssociationType::CODE_CROSS_SELLING));

        $this->facade()->removeAssociation($product->getId(), $complementary->getId(), ProductAssociationType::CODE_CROSS_SELLING);

        self::assertNull($this->relation($product, $complementary, ProductAssociationType::CODE_CROSS_SELLING));
        self::assertNull($this->relation($complementary, $product, ProductAssociationType::CODE_CROSS_SELLING));
    }

    public function testFacadeRefusesToAssociateAProductWithItself(): void
    {
        $product = $this->product();

        $this->expectException(SelfAssociationException::class);

        $this->facade()->addAssociation($product->getId(), $product->getId(), ProductAssociationType::CODE_ACCESSORY);
    }

    public function testFacadeRefusesAnUnknownType(): void
    {
        $product = $this->product();
        $accessory = $this->product();

        $this->expectException(ProductAssociationTypeNotFoundException::class);

        $this->facade()->addAssociation($product->getId(), $accessory->getId(), 'no_such_type');
    }

    public function testDeletingAProductTakesTheRelationsThatPointAtIt(): void
    {
        $this->useTransaction = false;

        $product = $this->product();
        $accessory = $this->product();

        try {
            $this->add($product, $accessory, ProductAssociationType::CODE_ACCESSORY);

            $this->dispatcher->dispatch(
                new ProductDeleteEvent($accessory->getId()),
                TheliaEvents::PRODUCT_DELETE,
            );

            self::assertSame(
                0,
                AccessoryQuery::create()->filterByProductId($product->getId())->count(),
                'A relation pointing at a deleted product goes with it',
            );
        } finally {
            AccessoryQuery::create()->filterByProductId($product->getId())->delete();
            $product->delete();
        }
    }

    private function product(): Product
    {
        return $this->factory->product($this->category, $this->taxRule, $this->currency);
    }

    private function add(Product $product, Product $associatedProduct, string $typeCode): void
    {
        $this->dispatcher->dispatch(
            new ProductAddAssociationEvent($product, $associatedProduct->getId(), $typeCode),
            TheliaEvents::PRODUCT_ADD_ASSOCIATION,
        );
    }

    private function relation(Product $product, Product $associatedProduct, string $typeCode): ?Accessory
    {
        return AccessoryQuery::create()
            ->filterByProductId($product->getId())
            ->filterByAccessory($associatedProduct->getId())
            ->useProductAssociationTypeQuery()
                ->filterByCode($typeCode)
            ->endUse()
            ->findOne();
    }

    private function facade(): ProductFacade
    {
        return $this->getService(ProductFacade::class);
    }

    private function listenTo(string $eventName, callable $listener, callable $body): void
    {
        $dispatcher = $this->dispatcher;
        self::assertInstanceOf(SymfonyEventDispatcherInterface::class, $dispatcher);

        $dispatcher->addListener($eventName, $listener, -1024);

        try {
            $body();
        } finally {
            $dispatcher->removeListener($eventName, $listener);
        }
    }
}
