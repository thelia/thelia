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

namespace TheliaSmarty\Tests\Template\Plugin\Controller;

use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Response;

/**
 * Class TestController.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TestController extends BaseFrontController
{
    public function testAction()
    {
        return new Response('world');
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
        return $this->getRequest()->query->get('foo');
    }

    public function testRequestAction()
    {
        return $this->getRequest()->request->get('foo').$this->getRequest()->getMethod();
    }
}
