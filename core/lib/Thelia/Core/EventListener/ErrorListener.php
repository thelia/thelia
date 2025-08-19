<?php

declare(strict_types=1);

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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\TheliaKernelEvents;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Class ErrorListener.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ErrorListener
{
    private ParserInterface $parser;

    /**
     * @throws \Exception
     */
    public function __construct(
        protected $env,
        protected ParserResolver $parserResolver,
        protected SecurityContext $securityContext,
        protected EventDispatcherInterface $eventDispatcher,
    ) {
        $this->parser = $this->parserResolver->getParserByCurrentRequest();
    }

    /**
     * @throws \Exception
     */
    #[AsEventListener(event: TheliaKernelEvents::THELIA_HANDLE_ERROR, priority: 0)]
    public function defaultErrorFallback(ExceptionEvent $event): void
    {
        $this->parser->assign('status_code', 500);
        $this->parser->assign('exception_message', $event->getThrowable()->getMessage());

        if (!$this->parser->hasTemplateDefinition()) {
            $this->parser->setTemplateDefinition(
                $this->securityContext->hasAdminUser() ?
                    $this->parser->getTemplateHelper()->getActiveAdminTemplate() :
                    $this->parser->getTemplateHelper()->getActiveFrontTemplate(),
            );
        }

        $response = new Response(
            $this->parser->render(ConfigQuery::getErrorMessagePageName()),
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );

        $event->setResponse($response);
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
    public function handleException(ExceptionEvent $event): void
    {
        if ($event->getRequest()->get('_api_operation_name', false)) {
            return;
        }

        if ('prod' === $this->env && ConfigQuery::isShowingErrorMessage()) {
            $this->eventDispatcher
                ->dispatch(
                    $event,
                    TheliaKernelEvents::THELIA_HANDLE_ERROR,
                );
        }
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
    public function logException(ExceptionEvent $event): void
    {
        // Log exception in the Thelia log
        $exception = $event->getThrowable();

        $logMessage = '';

        do {
            $logMessage .=
                ('' !== $logMessage && '0' !== $logMessage ? \PHP_EOL.'Caused by' : 'Uncaught exception')
                .$exception->getMessage()
                .\PHP_EOL
                .'Stack Trace: '.$exception->getTraceAsString();
        } while (($exception = $exception->getPrevious()) instanceof \Throwable);

        Tlog::getInstance()->error($logMessage);

        if (null !== $exception) {
            Tlog::getInstance()->error($exception->getTraceAsString());
        }
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 100)]
    public function authenticationException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof AuthenticationException) {
            $event->setResponse(
                new RedirectResponse($exception->getLoginTemplate()),
            );
        }
    }

}
