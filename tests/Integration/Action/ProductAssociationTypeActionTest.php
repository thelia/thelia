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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ProductAssociationType\ProductAssociationTypeCreateEvent;
use Thelia\Core\Event\ProductAssociationType\ProductAssociationTypeDeleteEvent;
use Thelia\Core\Event\ProductAssociationType\ProductAssociationTypeToggleVisibleEvent;
use Thelia\Core\Event\ProductAssociationType\ProductAssociationTypeUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\ProductAssociationType;
use Thelia\Model\ProductAssociationTypeI18nQuery;
use Thelia\Model\ProductAssociationTypeQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class ProductAssociationTypeActionTest extends IntegrationTestCase
{
    private EventDispatcherInterface $dispatcher;
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->getService(EventDispatcherInterface::class);
        $this->factory = $this->createFixtureFactory();
    }

    public function testCreateWritesTheTypeWithItsTitle(): void
    {
        $shopLocale = $this->shopLocale();

        $event = new ProductAssociationTypeCreateEvent();
        $event
            ->setCode('spare_parts')
            ->setTitle('Spare parts')
            ->setDescription('Parts that keep this product running')
            ->setVisible(1)
            ->setReciprocal(0)
            ->setLocale($shopLocale);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_ASSOCIATION_TYPE_CREATE);

        $type = $event->getProductAssociationType();
        self::assertNotNull($type);
        self::assertSame('spare_parts', $type->getCode());
        self::assertSame(1, $type->getVisible());
        self::assertSame(0, $type->getReciprocal());

        $type->setLocale($shopLocale);
        self::assertSame('Spare parts', $type->getTitle());
    }

    public function testCreateFillsTheShopLanguageWhenWrittenInAnother(): void
    {
        $otherLocale = $this->otherLocale();

        $event = new ProductAssociationTypeCreateEvent();
        $event
            ->setCode('bundles')
            ->setTitle('Lots')
            ->setVisible(1)
            ->setReciprocal(0)
            ->setLocale($otherLocale);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_ASSOCIATION_TYPE_CREATE);

        $shopWording = ProductAssociationTypeI18nQuery::create()
            ->filterById($event->getProductAssociationType()->getId())
            ->filterByLocale($this->shopLocale())
            ->findOne();

        self::assertNotNull($shopWording, 'The shop language never ends up without a title');
        self::assertSame('Lots', $shopWording->getTitle());
    }

    public function testUpdateRewordsTheTypeAndLeavesItsCodeAlone(): void
    {
        $type = $this->freshType('house_selection');
        $shopLocale = $this->shopLocale();

        $event = new ProductAssociationTypeUpdateEvent($type->getId());
        $event
            ->setCode('something_else')
            ->setTitle('Our selection')
            ->setDescription(null)
            ->setVisible(0)
            ->setReciprocal(1)
            ->setLocale($shopLocale);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_ASSOCIATION_TYPE_UPDATE);

        $reread = ProductAssociationTypeQuery::create()->findPk($type->getId());
        self::assertSame('house_selection', $reread->getCode(), 'The code is the stable identifier the core holds the type by');
        self::assertSame(0, $reread->getVisible());
        self::assertSame(1, $reread->getReciprocal());

        $reread->setLocale($shopLocale);
        self::assertSame('Our selection', $reread->getTitle());
    }

    public function testDeleteRefusesTheTypeTheShopCannotDoWithout(): void
    {
        $accessory = ProductAssociationTypeQuery::create()
            ->filterByCode(ProductAssociationType::CODE_ACCESSORY)
            ->findOne();

        self::assertNotNull($accessory);

        $this->expectException(\LogicException::class);

        try {
            $this->dispatcher->dispatch(
                new ProductAssociationTypeDeleteEvent($accessory->getId()),
                TheliaEvents::PRODUCT_ASSOCIATION_TYPE_DELETE,
            );
        } finally {
            self::assertNotNull(ProductAssociationTypeQuery::create()->findPk($accessory->getId()));
        }
    }

    public function testDeleteRefusesATypeThatStillHoldsRelations(): void
    {
        $type = $this->freshType('kits');

        $category = $this->factory->category();
        $currency = $this->factory->currency();
        $taxRule = $this->factory->taxRule();
        $product = $this->factory->product($category, $taxRule, $currency);
        $related = $this->factory->product($category, $taxRule, $currency);

        $this->factory->association($product, $related, $type->getCode(), 1);

        $this->expectException(\LogicException::class);

        try {
            $this->dispatcher->dispatch(
                new ProductAssociationTypeDeleteEvent($type->getId()),
                TheliaEvents::PRODUCT_ASSOCIATION_TYPE_DELETE,
            );
        } finally {
            self::assertNotNull(
                ProductAssociationTypeQuery::create()->findPk($type->getId()),
                'A type still in use stays, so its relations never go orphan',
            );
        }
    }

    public function testDeleteRemovesATypeNobodyUses(): void
    {
        $type = $this->freshType('seasonal');

        $this->dispatcher->dispatch(
            new ProductAssociationTypeDeleteEvent($type->getId()),
            TheliaEvents::PRODUCT_ASSOCIATION_TYPE_DELETE,
        );

        self::assertNull(ProductAssociationTypeQuery::create()->findPk($type->getId()));
    }

    public function testToggleVisibleFlipsTheFlagBothWays(): void
    {
        $type = $this->freshType('later');
        self::assertSame(1, $type->getVisible());

        $this->dispatcher->dispatch(
            new ProductAssociationTypeToggleVisibleEvent($type->getId()),
            TheliaEvents::PRODUCT_ASSOCIATION_TYPE_TOGGLE_VISIBLE,
        );
        self::assertSame(0, ProductAssociationTypeQuery::create()->findPk($type->getId())->getVisible());

        $this->dispatcher->dispatch(
            new ProductAssociationTypeToggleVisibleEvent($type->getId()),
            TheliaEvents::PRODUCT_ASSOCIATION_TYPE_TOGGLE_VISIBLE,
        );
        self::assertSame(1, ProductAssociationTypeQuery::create()->findPk($type->getId())->getVisible());
    }

    public function testUpdatePositionMovesATypeUp(): void
    {
        $first = $this->freshType('first_block');
        $second = $this->freshType('second_block');

        self::assertGreaterThan($first->getPosition(), $second->getPosition());

        $this->dispatcher->dispatch(
            new UpdatePositionEvent($second->getId(), UpdatePositionEvent::POSITION_UP),
            TheliaEvents::PRODUCT_ASSOCIATION_TYPE_UPDATE_POSITION,
        );

        $firstAfter = ProductAssociationTypeQuery::create()->findPk($first->getId())->getPosition();
        $secondAfter = ProductAssociationTypeQuery::create()->findPk($second->getId())->getPosition();

        self::assertLessThan($firstAfter, $secondAfter);
    }

    private function freshType(string $code): ProductAssociationType
    {
        $event = new ProductAssociationTypeCreateEvent();
        $event
            ->setCode($code)
            ->setTitle(ucfirst(str_replace('_', ' ', $code)))
            ->setVisible(1)
            ->setReciprocal(0)
            ->setLocale($this->shopLocale());

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_ASSOCIATION_TYPE_CREATE);

        return $event->getProductAssociationType();
    }

    private function shopLocale(): string
    {
        return (string) Lang::getDefaultLanguage()->getLocale();
    }

    private function otherLocale(): string
    {
        $other = LangQuery::create()
            ->filterByLocale($this->shopLocale(), \Propel\Runtime\ActiveQuery\Criteria::NOT_EQUAL)
            ->findOne();

        if (null === $other) {
            self::markTestSkipped('The shop has a single language, so no other one to write in.');
        }

        return (string) $other->getLocale();
    }
}
