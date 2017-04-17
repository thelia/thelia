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

use Thelia\Action\Config;
use Thelia\Core\Event\Config\ConfigCreateEvent;
use Thelia\Core\Event\Config\ConfigDeleteEvent;
use Thelia\Core\Event\Config\ConfigUpdateEvent;
use Thelia\Model\Config as ConfigModel;
use Thelia\Model\ConfigQuery;

/**
 * Class ConfigTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ConfigTest extends BaseAction
{
    public static function setUpBeforeClass()
    {
        ConfigQuery::create()
            ->filterByName('foo')
            ->delete();
    }

    public function testCreate()
    {
        $event = new ConfigCreateEvent();

        $event
            ->setEventName('foo')
            ->setValue('bar')
            ->setLocale('en_US')
            ->setTitle('test config foo bar')
            ->setHidden(true)
            ->setSecured(true)
        ;

        $action = new Config();
        $action->create($event, null, $this->getMockEventDispatcher());

        $createdConfig = $event->getConfig();

        $this->assertInstanceOf('Thelia\Model\Config', $createdConfig);

        $this->assertFalse($createdConfig->isNew());

        $this->assertEquals('foo', $createdConfig->getName());
        $this->assertEquals('bar', $createdConfig->getValue());
        $this->assertEquals('en_US', $createdConfig->getLocale());
        $this->assertEquals('test config foo bar', $createdConfig->getTitle());
        $this->assertEquals(1, $createdConfig->getHidden());
        $this->assertEquals(1, $createdConfig->getSecured());

        return $createdConfig;
    }

    /**
     * @param ConfigModel $config
     * @depends testCreate
     * @return ConfigModel
     */
    public function testSetValue(ConfigModel $config)
    {
        $event = new ConfigUpdateEvent($config->getId());
        $event
            ->setValue('baz');

        $action = new Config();
        $action->setValue($event, null, $this->getMockEventDispatcher());

        $updatedConfig = $event->getConfig();

        $this->assertInstanceOf('Thelia\Model\Config', $updatedConfig);

        $this->assertEquals($config->getName(), $updatedConfig->getName());
        $this->assertEquals('baz', $updatedConfig->getValue());
        $this->assertEquals($config->getLocale(), $updatedConfig->getLocale());
        $this->assertEquals($config->getTitle(), $updatedConfig->getTitle());
        $this->assertEquals($config->getHidden(), $updatedConfig->getHidden());
        $this->assertEquals($config->getSecured(), $updatedConfig->getSecured());

        return $updatedConfig;
    }

    /**
     * @param ConfigModel $config
     * @depends testSetValue
     * @return ConfigModel
     */
    public function testModify(ConfigModel $config)
    {
        $event = new ConfigUpdateEvent($config->getId());
        $event
            ->setEventName('foo')
            ->setValue('update baz')
            ->setLocale('en_US')
            ->setTitle('config title')
            ->setDescription('config description')
            ->setChapo('config chapo')
            ->setPostscriptum('config postscriptum')
            ->setHidden(0)
            ->setSecured(0)
        ;

        $action = new Config();
        $action->modify($event, null, $this->getMockEventDispatcher());

        $updatedConfig = $event->getConfig();

        $this->assertInstanceOf('Thelia\Model\Config', $updatedConfig);

        $this->assertEquals('foo', $updatedConfig->getName());
        $this->assertEquals('update baz', $updatedConfig->getValue());
        $this->assertEquals('en_US', $updatedConfig->getLocale());
        $this->assertEquals('config title', $updatedConfig->getTitle());
        $this->assertEquals('config description', $updatedConfig->getDescription());
        $this->assertEquals('config chapo', $updatedConfig->getChapo());
        $this->assertEquals('config postscriptum', $updatedConfig->getPostscriptum());
        $this->assertEquals(0, $updatedConfig->getHidden());
        $this->assertEquals(0, $updatedConfig->getSecured());

        return $updatedConfig;
    }

    /**
     * @param ConfigModel $config
     * @depends testModify
     */
    public function testDelete(ConfigModel $config)
    {
        $event = new ConfigDeleteEvent($config->getId());

        $action = new Config();
        $action->delete($event, null, $this->getMockEventDispatcher());

        $deletedConfig = $event->getConfig();

        $this->assertInstanceOf('Thelia\Model\Config', $deletedConfig);
        $this->assertTrue($deletedConfig->isDeleted());
    }
}
