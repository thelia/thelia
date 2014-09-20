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

namespace Thelia\Tests\FileFormat\Formatting;

use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\FileFormat\Formatting\AbstractFormatter;
use Thelia\Core\FileFormat\Formatting\FormatterManager;
use Thelia\Core\Translation\Translator;

/**
 * Class FormatterManagerTest
 * @package Thelia\Tests\FileFormat\Formatting
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class FormatterManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FormatterManager
     */
    protected $manager;

    public function setUp()
    {
        new Translator(
            new Container()
        );
        $this->manager = new FormatterManager();
    }

    public function testAddFormatter()
    {
        /** @var AbstractFormatter $instance */
        $instance = $this->getMock("Thelia\\Core\\FileFormat\\Formatting\\AbstractFormatter");

        $this->manager->add($instance);

        $archiveBuilders = $this->manager->getAll();

        $this->assertTrue(
            array_key_exists($instance->getName(), $archiveBuilders)
        );
    }

    public function testDeleteFormatter()
    {
        /** @var AbstractFormatter $instance */
        $instance = $this->getMock("Thelia\\Core\\FileFormat\\Formatting\\AbstractFormatter");

        $this->manager->add($instance);

        $this->manager->delete($instance->getName());

        $this->assertTrue(
            count($this->manager->getAll()) === 0
        );
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testDeleteNotExistingFormatter()
    {
        $this->manager->delete("foo");
    }
}
