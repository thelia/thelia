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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\TheliaKernelEvents;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

/**
 * Class ErrorListener
 * @package Thelia\Core\EventListener
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ErrorListener implements EventSubscriberInterface
{
    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var ParserInterface */
    protected $parser;

    /** @var SecurityContext */
    protected $securityContext;

    /** @var string */
    protected $env;

    public function __construct(
        $env,
        ParserInterface $parser,
        SecurityContext $securityContext,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->env = $env;

        $this->parser = $parser;

        $this->securityContext = $securityContext;

        $this->eventDispatcher = $eventDispatcher;
    }

    public function defaultErrorFallback(GetResponseForExceptionEvent $event)
    {
        $this->parser->assign("status_code", 500);
        $this->parser->assign("exception_message", $event->getException()->getMessage());

        $this->parser->setTemplateDefinition(
            $this->securityContext->hasAdminUser() ?
            $this->parser->getTemplateHelper()->getActiveAdminTemplate() :
            $this->parser->getTemplateHelper()->getActiveFrontTemplate()
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
            $this->eventDispatcher
                ->dispatch(
                    TheliaKernelEvents::THELIA_HANDLE_ERROR,
                    $event
                )
            ;
        }
    }

    public function logException(GetResponseForExceptionEvent $event)
    {
        // Log exception in the Thelia log
        $exception = $event->getException();
        
        $logMessage = '';
        
        do {
            $logMessage .=
                ($logMessage ? PHP_EOL . 'Caused by' : 'Uncaught exception')
                . $event->getException()->getMessage()
                . PHP_EOL
                . "Stack Trace: " . $event->getException()->getTraceAsString()
            ;
        } while (null !== $exception = $exception->getPrevious());
        
        Tlog::getInstance()->error($logMessage);
    }

    public function authenticationException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        if ($exception instanceof AuthenticationException) {
            $event->setResponse(
                RedirectResponse::create($exception->getLoginTemplate())
            );
        }
    }

    /**
     * {@inheritdoc}
     * api
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::EXCEPTION => [
                ["logException", 0],
                ["handleException", 0],
                ['authenticationException', 100]
            ],
            TheliaKernelEvents::THELIA_HANDLE_ERROR => [
                ["defaultErrorFallback", 0],
            ],
        );
    }
}
