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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException as BaseHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Exception\AdminAccessDenied;
use Thelia\Model\ConfigQuery;

/**
 * Class HttpException.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 * @author Manuel Raynaud  <manu@raynaud.io>
 */
class HttpException extends BaseAction implements EventSubscriberInterface
{
    protected ?ParserInterface $parser = null;

    public function __construct(
        protected ParserResolver $parserResolver,
        protected TemplateHelperInterface $templateHelper,
    ) {
        $this->parser = $this->parserResolver->getDefaultParser();
    }

    public function checkHttpException(ExceptionEvent $event): void
    {
        if ($event->getRequest()->get('isApiRoute', false)) {
            return;
        }

        $exception = $event->getThrowable();
        if ($exception instanceof NotFoundHttpException) {
            $this->display404($event);
        }

        if ($exception instanceof AdminAccessDenied) {
            $this->displayAdminGeneralError($event);
        }

        if ($exception instanceof BaseHttpException && !$event->getResponse() instanceof Response) {
            $this->displayException($event);
        }
    }

    protected function displayAdminGeneralError(ExceptionEvent $event): void
    {
        $activeAdminTemplate = $this->templateHelper->getActiveAdminTemplate();
        $this->parser = $this->parserResolver->getParser(
            $activeAdminTemplate->getAbsolutePath(),
            'general_error'
        );
        $this->parser->setTemplateDefinition(
            $activeAdminTemplate,
        );

        $message = $event->getThrowable()->getMessage();

        $response = new Response(
            $this->parser->render(
                'general_error',
                [
                    'error_message' => $message,
                ]
            ),
            Response::HTTP_FORBIDDEN
        );

        $event->setResponse($response);
    }

    protected function display404(ExceptionEvent $event): void
    {
        $this->parser->setTemplateDefinition(
            $this->parser->getTemplateHelper()->getActiveFrontTemplate()
        );

        $response = new Response($this->parser->render(ConfigQuery::getPageNotFoundView()), Response::HTTP_NOT_FOUND);

        $event->setResponse($response);
    }

    protected function displayException(ExceptionEvent $event): void
    {
        /** @var BaseHttpException $exception */
        $exception = $event->getThrowable();
        $event->setResponse(
            new Response(
                $exception->getMessage(),
                $exception->getStatusCode(),
                $exception->getHeaders()
            )
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['checkHttpException', 128],
        ];
    }
}
