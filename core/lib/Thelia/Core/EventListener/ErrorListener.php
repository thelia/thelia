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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\TheliaHttpKernel;
use Thelia\Core\TheliaKernelEvents;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

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
        if (!$this->isAuthorizedToSeeErrorDetails($event)) {
            return;
        }
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
        if (!$this->isAuthorizedToSeeErrorDetails($event)) {
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
        if (!$this->isAuthorizedToSeeErrorDetails($event)) {
            return;
        }

        $throwable = $event->getThrowable();
        $root = $throwable; // keep original reference

        $request = $event->getRequest();

        $logMessage = \sprintf(
            "Uncaught exception on %s %s\n%s: %s in %s:%d (code %s)",
            $request->getMethod(),
            $request->getUri(),
            $root::class,
            $root->getMessage(),
            $root->getFile(),
            $root->getLine(),
            $root->getCode()
        );

        // Append chained exceptions with their own stack
        $e = $root;
        do {
            $logMessage .= \sprintf(
                "\n--- Caused by %s: %s in %s:%d (code %s)\nStack trace:\n%s",
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getCode(),
                $e->getTraceAsString()
            );
        } while ($e = $e->getPrevious());

        Tlog::getInstance()->error($logMessage);
    }

    /**
     * Limit request payload size to avoid huge logs.
     */
    private function truncateArray(array $data, int $maxLength = 2048): array
    {
        $encoded = json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($encoded !== null && \strlen($encoded) > $maxLength) {
            // naive truncation: preserve start/end
            $half = (int) ($maxLength / 2);
            $encoded = substr($encoded, 0, $half).'…[truncated]…'.substr($encoded, -$half);
        }

        // Try to decode back, fallback to a wrapper if decode fails
        $decoded = json_decode((string) $encoded, true);
        if (\is_array($decoded)) {
            return $decoded;
        }

        return ['raw_truncated' => $encoded];
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 100)]
    public function authenticationException(ExceptionEvent $event): void
    {
        if (!$this->isAuthorizedToSeeErrorDetails($event)) {
            return;
        }
        $exception = $event->getThrowable();

        if ($exception instanceof AuthenticationException) {
            $event->setResponse(
                new RedirectResponse($exception->getLoginTemplate()),
            );
        }
    }

    private function isAuthorizedToSeeErrorDetails(ExceptionEvent $event): bool
    {
        $request = $event->getRequest();

        return $event->isMainRequest()
            && !$request->attributes->get(TheliaHttpKernel::IGNORE_THELIA_VIEW, false)
            && !$request->get('_api_operation_name', false);
    }
}
