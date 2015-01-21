<?php
/*************************************************************************************/
/* This file is part of the Thelia package.                                          */
/*                                                                                   */
/* Copyright (c) OpenStudio                                                          */
/* email : dev@thelia.net                                                            */
/* web : http://www.thelia.net                                                       */
/*                                                                                   */
/* For the full copyright and license information, please view the LICENSE.txt       */
/* file that was distributed with this source code.                                  */
/*************************************************************************************/

namespace TheliaSmarty\Tests\Template\Plugin\Controller;

use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Response;

/**
 * Class TestController
 * @package TheliaSmarty\Tests\Template\Plugin\Controller
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TestController extends BaseFrontController
{
    public function testAction()
    {
        return new Response("world");
    }

    public function testParamsAction($paramA, $paramB)
    {
        return new Response($paramA.$paramB);
    }

    public function testMethodAction()
    {
        return $this->getRequest()->getMethod();
    }

    public function testQueryAction()
    {
        return $this->getRequest()->query->get("foo");
    }

    public function testRequestAction()
    {
        return $this->getRequest()->request->get("foo").$this->getRequest()->getMethod();
    }
}
