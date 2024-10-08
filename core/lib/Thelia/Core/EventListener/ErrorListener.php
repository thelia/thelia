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

namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\TheliaKernelEvents;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

/**
 * Class ErrorListener.
 *
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
        $kernelEnvironment,
        ParserInterface $parser,
        SecurityContext $securityContext,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->env = $kernelEnvironment;

        $this->parser = $parser;

        $this->securityContext = $securityContext;

        $this->eventDispatcher = $eventDispatcher;
    }

    public function defaultErrorFallback(ExceptionEvent $event): void
    {
        $this->parser->assign('status_code', 500);
        $this->parser->assign('exception_message', $event->getThrowable()->getMessage());

        if (!$this->parser->hasTemplateDefinition()) {
            $this->parser->setTemplateDefinition(
                $this->securityContext->hasAdminUser() ?
                    $this->parser->getTemplateHelper()->getActiveAdminTemplate() :
                    $this->parser->getTemplateHelper()->getActiveFrontTemplate()
            );
        }

        $response = new Response(
            $this->parser->render(ConfigQuery::getErrorMessagePageName()),
            500
        );

        $event->setResponse($response);
    }

    public function handleException(ExceptionEvent $event): void
    {
        if($event->getRequest()->get('_api_operation_name',false)){
            return;
        }
        if ('prod' === $this->env && ConfigQuery::isShowingErrorMessage()) {
            $this->eventDispatcher
                ->dispatch(
                    $event,
                    TheliaKernelEvents::THELIA_HANDLE_ERROR
                )
            ;
        }
    }

    public function logException(ExceptionEvent $event): void
    {
        // Log exception in the Thelia log
        $exception = $event->getThrowable();

        $logMessage = '';

        do {
            $logMessage .=
                ($logMessage ? \PHP_EOL.'Caused by' : 'Uncaught exception')
                .$exception->getMessage()
                .\PHP_EOL
                .'Stack Trace: '.$exception->getTraceAsString()
            ;
        } while (null !== $exception = $exception->getPrevious());

        Tlog::getInstance()->error($logMessage);
        if ($exception !== null) {
            Tlog::getInstance()->error($exception->getTraceAsString());
        }
    }

    public function authenticationException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof AuthenticationException) {
            $event->setResponse(
                new RedirectResponse($exception->getLoginTemplate())
            );
        }
    }

    /**
     * {@inheritdoc}
     * api.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['logException', 0],
                ['handleException', 0],
                ['authenticationException', 100],
            ],
            TheliaKernelEvents::THELIA_HANDLE_ERROR => [
                ['defaultErrorFallback', 0],
            ],
        ];
    }
}
