<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tests\Action;

use Thelia\Model\MetaDataQuery;

use Product;
use Thelia\Action\Message;
use Thelia\Action\MetaData;
use Thelia\Core\Event\Message\MessageDeleteEvent;
use Thelia\Core\Event\Message\MessageUpdateEvent;
use Thelia\Core\Event\MetaData\MetaDataCreateOrUpdateEvent;
use Thelia\Core\Event\MetaData\MetaDataEvent;
use Thelia\Model\Base\ProductQuery;
use Thelia\Model\Message as MessageModel;
use Thelia\Core\Event\Message\MessageCreateEvent;
use Thelia\Model\MessageQuery;


/**
 * Class MetaDataTest
 * @package Thelia\Tests\Action
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class MetaDataTest extends \PHPUnit_Framework_TestCase
{
    protected $dispatcher;

    public function setUp()
    {
        $this->dispatcher = $this->getMock("Symfony\\Component\\EventDispatcher\\EventDispatcherInterface");
    }

    public static function setUpBeforeClass()
    {
        $boom = MetaDataQuery::create()
            ->delete();
    }


    public function testCreate()
    {
        // get a product
        $product = ProductQuery::create()->findOne();

        // simple
        $event = new MetaDataCreateOrUpdateEvent();
        $event
            ->setMetaKey('test')
            ->setElementId(get_class($product))
            ->setElementId($product->getId())
            ->setValue('test')
            ->setDispatcher($this->dispatcher);

        $action = new MetaData();
        $action->createOrUpdate($event);

        $created = $event->getMetaData();

        $this->assertInstanceOf('Thelia\Model\MetaData', $created);

        $this->assertFalse($created->isNew());

        $this->assertEquals('test', $created->getMetaKey());
        $this->assertEquals(get_class($product), $created->getElementKey());
        $this->assertEquals($product->getId(), $created->getElementId());
        $this->assertEquals('test', $created->getValue());
        $this->assertEquals(false, $created->getIsSerialized());

        // complex
        $event = new MetaDataCreateOrUpdateEvent();
        $event
            ->setMetaKey('test2')
            ->setElementId(get_class($product))
            ->setElementId($product->getId())
            ->setValue(array("fr_FR" => "bonjour", "en_US" => "Hello"))
            ->setDispatcher($this->dispatcher);
        $action = new MetaData();
        $action->createOrUpdate($event);

        $created = $event->getMetaData();

        $this->assertInstanceOf('Thelia\Model\MetaData', $created);

        $this->assertFalse($created->isNew());

        $this->assertEquals('test2', $created->getMetaKey());
        $this->assertEquals(get_class($product), $created->getElementKey());
        $this->assertEquals($product->getId(), $created->getElementId());
        $this->assertEquals(array("fr_FR" => "bonjour", "en_US" => "Hello"), $created->getValue());
        $this->assertEquals(true, $created->getIsSerialized());

        return $product;
    }

}
