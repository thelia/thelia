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

namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelper;
use Thelia\Core\TheliaKernelEvents;
use Thelia\Model\ConfigQuery;

/**
 * Class ErrorListener
 * @package Thelia\Core\EventListener
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ErrorListener implements EventSubscriberInterface
{
    /**
     * @var ParserInterface
     */
    protected $parser;

    /**
     * @var SecurityContext
     */
    protected $securityContext;

    protected $env;

    public function __construct($env, ParserInterface $parser, SecurityContext $securityContext)
    {
        $this->env = $env;

        $this->parser = $parser;

        $this->securityContext = $securityContext;
    }

    public function defaultErrorFallback(GetResponseForExceptionEvent $event)
    {
        $this->parser->assign("status_code", 500);
        $this->parser->assign("exception_message", $event->getException()->getMessage());

        $this->parser->setTemplateDefinition(
            $this->securityContext->hasAdminUser() ?
            TemplateHelper::getInstance()->getActiveAdminTemplate() :
            TemplateHelper::getInstance()->getActiveFrontTemplate()
        );

        $response = new Response(
            $this->parser->render(ConfigQuery::getErrorMessagePageName()),
            500
        );

        $event->setResponse($response);
    }

    public function handleException(GetResponseForExceptionEvent $event)
    {
        if ("prod" === $this->env && ConfigQuery::isShowingErrorMessage()) {
            $event->getDispatcher()
                ->dispatch(
                    TheliaKernelEvents::THELIA_HANDLE_ERROR,
                    $event
                )
            ;
        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::EXCEPTION => [
                ["handleException", 0]
            ],
            TheliaKernelEvents::THELIA_HANDLE_ERROR => [
                ["defaultErrorFallback", 0],
            ],
        );
    }
}
