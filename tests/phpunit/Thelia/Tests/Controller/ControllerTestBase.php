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

use Thelia\Controller\BaseController;
use Thelia\Tests\ContainerAwareTestCase;

/**
 * Class ControllerTestBase
 * @package Thelia\Tests\ImportExport\Import
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class ControllerTestBase extends ContainerAwareTestCase
{
    /** @var BaseController  */
    protected $controller;

    public function setUp()
    {
        parent::setUp();

        $this->controller = $this->getController();
        $this->controller->setContainer($this->container);
    }

    /**
     * @return \Thelia\Controller\BaseController The controller you want to test
     */
    abstract protected function getController();
}
