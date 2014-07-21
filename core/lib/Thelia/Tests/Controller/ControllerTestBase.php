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

namespace Thelia\Tests\Controller;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Controller\Admin\ImportController;
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\TarArchiveBuilder;
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\TarBz2ArchiveBuilder;
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\TarGzArchiveBuilder;
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\ZipArchiveBuilder;
use Thelia\Core\FileFormat\Archive\ArchiveBuilderManager;
use Thelia\Core\FileFormat\Formatting\Formatter\JsonFormatter;
use Thelia\Core\FileFormat\Formatting\Formatter\XMLFormatter;
use Thelia\Core\FileFormat\Formatting\FormatterManager;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;

/**
 * Class ControllerTestBase
 * @package Thelia\Tests\ImportExport\Import
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class ControllerTestBase extends \PHPUnit_Framework_TestCase
{
    protected $import;

    /** @var ContainerInterface */
    protected $container;

    /** @var  Session */
    protected $session;

    /** @var ImportController  */
    protected $controller;

    public function getContainer()
    {
        $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
        $container->set("thelia.translator", new Translator(new Container()));

        $dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");

        $container->set("event_dispatcher", $dispatcher);

        $request = new Request();
        $request->setSession($this->session);

        $container->set("request", $request);

        $this->buildContainer($container);

        return $container;
    }

    public function getSession()
    {
        return new Session();
    }

    public function setUp()
    {

        Tlog::getNewInstance();

        $this->session = $this->getSession();
        $this->container = $this->getContainer();
        $this->controller = $this->getController();
        $this->controller->setContainer($this->container);

    }

    /**
     * @return \Thelia\Controller\BaseController The controller you want to test
     */
    abstract protected  function getController();

    /**
     * Use this method to build the container with the services that you need.
     */
    abstract protected function buildContainer(ContainerBuilder $container);

} 