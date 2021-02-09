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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
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

        $event = $this->getRequestEvent();
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

    protected function getRequestEvent(): RequestEvent
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        /** @var HttpKernelInterface $kernelMock */
        $kernelMock = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')
            ->disableOriginalConstructor()
            ->getMock();

        return new RequestEvent($kernelMock, $request, HttpKernelInterface::MASTER_REQUEST);
    }

    protected function getRequestListener()
    {
        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $translator = new Translator($requestStack);
        $eventDispatcher = new EventDispatcher();

        return new RequestListener($translator, $eventDispatcher);
    }
}
