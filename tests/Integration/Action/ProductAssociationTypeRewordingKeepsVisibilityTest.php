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
use Thelia\Core\Event\ProductAssociationType\ProductAssociationTypeToggleVisibleEvent;
use Thelia\Core\Event\ProductAssociationType\ProductAssociationTypeUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Map\ProductAssociationTypeTableMap;
use Thelia\Model\ProductAssociationType;
use Thelia\Model\ProductAssociationTypeQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * Visibility and reciprocity have their own event; rewording a type must not
 * carry them back to the defaults of the update event.
 */
final class ProductAssociationTypeRewordingKeepsVisibilityTest extends IntegrationTestCase
{
    public function testRewordingAHiddenTypeLeavesItHidden(): void
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);

        $type = ProductAssociationTypeQuery::create()
            ->findOneByCode(ProductAssociationType::CODE_CROSS_SELLING);

        self::assertInstanceOf(ProductAssociationType::class, $type);
        self::assertSame(1, $type->getReciprocal(), 'The install seeds cross_selling as a reciprocal type.');

        $dispatcher->dispatch(
            new ProductAssociationTypeToggleVisibleEvent($type->getId()),
            TheliaEvents::PRODUCT_ASSOCIATION_TYPE_TOGGLE_VISIBLE,
        );

        self::assertSame(0, $this->reload($type->getId())->getVisible(), 'The toggle hid the type.');

        $dispatcher->dispatch(
            (new ProductAssociationTypeUpdateEvent($type->getId()))
                ->setLocale('en_US')
                ->setTitle('Reworded'),
            TheliaEvents::PRODUCT_ASSOCIATION_TYPE_UPDATE,
        );

        $reworded = $this->reload($type->getId());

        self::assertSame(
            0,
            $reworded->getVisible(),
            'Rewording a type must not put back on sheets a type the merchant hid with the toggle event.',
        );
        self::assertSame(
            1,
            $reworded->getReciprocal(),
            'Rewording a type must not drop its reciprocity either.',
        );
    }

    private function reload(int $id): ProductAssociationType
    {
        ProductAssociationTypeTableMap::clearInstancePool();

        $type = ProductAssociationTypeQuery::create()->findPk($id);

        self::assertInstanceOf(ProductAssociationType::class, $type);

        return $type;
    }
}
