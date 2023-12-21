<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Action;

use Thelia\Action\MetaData;
use Thelia\Core\Event\MetaData\MetaDataCreateOrUpdateEvent;
use Thelia\Core\Event\MetaData\MetaDataDeleteEvent;
use Thelia\Model\MetaDataQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;

/**
 * Class MetaDataTest.
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class MetaDataTest extends BaseAction
{
    public static function setUpBeforeClass(): void
    {
        $boom = MetaDataQuery::create()
            ->deleteAll();
    }

    public function testCreate()
    {
        // get a product
        $product = ProductQuery::create()->findOne();

        // simple
        $event = new MetaDataCreateOrUpdateEvent();
        $event
            ->setMetaKey('test')
            ->setElementKey($product::class)
            ->setElementId($product->getId())
            ->setValue('test');

        $action = new MetaData();
        $action->createOrUpdate($event);

        $created = $event->getMetaData();

        $this->assertInstanceOf('Thelia\Model\MetaData', $created);

        $this->assertFalse($created->isNew());

        $this->assertEquals('test', $created->getMetaKey());
        $this->assertEquals($product::class, $created->getElementKey());
        $this->assertEquals($product->getId(), $created->getElementId());
        $this->assertEquals('test', $created->getValue());
        $this->assertFalse($created->getIsSerialized());

        // complex
        $event = new MetaDataCreateOrUpdateEvent();
        $event
            ->setMetaKey('test2')
            ->setElementKey($product::class)
            ->setElementId($product->getId())
            ->setValue(['fr_FR' => 'bonjour', 'en_US' => 'Hello']);
        $action = new MetaData();
        $action->createOrUpdate($event);

        $created = $event->getMetaData();

        $this->assertInstanceOf('Thelia\Model\MetaData', $created);

        $this->assertFalse($created->isNew());

        $this->assertEquals('test2', $created->getMetaKey());
        $this->assertEquals($product::class, $created->getElementKey());
        $this->assertEquals($product->getId(), $created->getElementId());
        $this->assertEquals(['fr_FR' => 'bonjour', 'en_US' => 'Hello'], $created->getValue());
        $this->assertTrue($created->getIsSerialized());

        return $product;
    }

    /**
     * @depends testCreate
     *
     * @return Product
     */
    public function testRead(Product $product)
    {
        $metaDatas = MetaDataQuery::create()
            ->filterByElementKey($product::class)
            ->filterByElementId($product->getId())
            ->find();

        $this->assertEquals($metaDatas->count(), 2);

        $metaData = MetaDataQuery::create()
            ->filterByMetaKey('test')
            ->filterByElementKey($product::class)
            ->filterByElementId($product->getId())
            ->findOne();

        $this->assertNotNull($metaData);
        $this->assertEquals('test', $metaData->getMetaKey());
        $this->assertEquals($product::class, $metaData->getElementKey());
        $this->assertEquals($product->getId(), $metaData->getElementId());
        $this->assertEquals('test', $metaData->getValue());

        $this->assertFalse($metaData->getIsSerialized());

        $datas = MetaDataQuery::getAllVal($product::class, $product->getId());
        $this->assertEquals(\count($datas), 2);
        $this->assertEquals($datas['test'], 'test');
        $this->assertEquals($datas['test2'], ['fr_FR' => 'bonjour', 'en_US' => 'Hello']);

        return $product;
    }

    /**
     * @depends testRead
     *
     * @return Product
     */
    public function testUpdate(Product $product)
    {
        $metaData = MetaDataQuery::create()
            ->filterByMetaKey('test')
            ->filterByElementKey($product::class)
            ->filterByElementId($product->getId())
            ->findOne();

        $this->assertNotNull($metaData);

        $event = new MetaDataCreateOrUpdateEvent();
        $event
            ->setMetaKey($metaData->getMetaKey())
            ->setElementKey($metaData->getElementKey())
            ->setElementId($metaData->getElementId())
            ->setValue(['fr_FR' => 'bonjour', 'en_US' => 'Hello']);

        $action = new MetaData();
        $action->createOrUpdate($event);

        $updated = $event->getMetaData();

        $this->assertInstanceOf('Thelia\Model\MetaData', $updated);

        $this->assertFalse($updated->isNew());

        $this->assertEquals('test', $updated->getMetaKey());
        $this->assertEquals($product::class, $updated->getElementKey());
        $this->assertEquals($product->getId(), $updated->getElementId());
        $this->assertEquals(['fr_FR' => 'bonjour', 'en_US' => 'Hello'], $updated->getValue());
        $this->assertTrue($updated->getIsSerialized());

        return $product;
    }

    /**
     * @depends testUpdate
     *
     * @return Product
     */
    public function testDelete(Product $product)
    {
        $metaData = MetaDataQuery::create()
            ->filterByMetaKey('test')
            ->filterByElementKey($product::class)
            ->filterByElementId($product->getId())
            ->findOne();

        $this->assertNotNull($metaData);

        $event = new MetaDataDeleteEvent('test', $product::class, $product->getId());

        $action = new MetaData();
        $action->delete($event);

        $deleted = $event->getMetaData();

        $this->assertInstanceOf('Thelia\Model\MetaData', $deleted);
        $this->assertTrue($deleted->isDeleted());
    }
}
