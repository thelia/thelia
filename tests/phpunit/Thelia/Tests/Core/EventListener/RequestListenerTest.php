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

namespace Thelia\Tests\Core\EventListener;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\EventListener\RequestListener;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CurrencyQuery;
use Thelia\Tests\WebTestCase;

/**
 * Class RequestListenerTest
 * @package Thelia\Tests\Core\EventListener
 * @author Gilles Bourgeat <gilles@thelia.net>
 */
class RequestListenerTest extends WebTestCase
{
    public function testCheckCurrency()
    {
        $listener = $this->getRequestListener();

        $event = $this->getGetResponseEvent();
        /** @var Session $session */
        $session = $event->getRequest()->getSession();

        // Test with a session that has no currency
        $listener->checkCurrency($event);
        $currentCurrency = $session->getCurrency();
        $this->assertInstanceOf('Thelia\Model\Currency', $currentCurrency);

        // Test change currency
        $newCurrency = CurrencyQuery::create()->filterById($currentCurrency->getId(), Criteria::NOT_IN)->findOne();
        $event->getRequest()->query->set('currency', $newCurrency->getCode());
        $listener->checkCurrency($event);
        $this->assertEquals($session->getCurrency()->getId(), $newCurrency->getId());
    }

    protected function getGetResponseEvent()
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        /** @var HttpKernelInterface $kernelMock */
        $kernelMock = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')
            ->disableOriginalConstructor()
            ->getMock();

        return new GetResponseEvent($kernelMock, $request, HttpKernelInterface::MASTER_REQUEST);
    }

    protected function getRequestListener()
    {
        $translator = new Translator(new Container());
        $eventDispatcher = new EventDispatcher();

        return new RequestListener($translator, $eventDispatcher);
    }
}
