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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException as BaseHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\ParserInterface;
use Thelia\Exception\AdminAccessDenied;
use Thelia\Model\ConfigQuery;

/**
 *
 * Class HttpException
 * @package Thelia\Action
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 * @author Manuel Raynaud  <manu@raynaud.io>
 */
class HttpException extends BaseAction implements EventSubscriberInterface
{
    /** @var ParserInterface */
    protected $parser;

    public function __construct(ParserInterface $parser)
    {
        $this->parser = $parser;
    }

    public function checkHttpException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();
        if ($exception instanceof NotFoundHttpException) {
            $this->display404($event);
        }

        if ($exception instanceof AdminAccessDenied) {
            $this->displayAdminGeneralError($event);
        }

        if ($exception instanceof BaseHttpException && null === $event->getResponse()) {
            $this->displayException($event);
        }
    }

    protected function displayAdminGeneralError(ExceptionEvent $event)
    {
        // Define the template thant shoud be used
        $this->parser->setTemplateDefinition(
            $this->parser->getTemplateHelper()->getActiveAdminTemplate()
        );

        $message = $event->getThrowable()->getMessage();

        $response = Response::create(
            $this->parser->render(
                'general_error.html',
                [
                    "error_message" => $message
                ]
            ),
            403
        );

        $event->setResponse($response);
    }

    protected function display404(ExceptionEvent $event)
    {
        // Define the template thant shoud be used
        $this->parser->setTemplateDefinition(
            $this->parser->getTemplateHelper()->getActiveFrontTemplate()
        );

        $response = new Response($this->parser->render(ConfigQuery::getPageNotFoundView()), 404);

        $event->setResponse($response);
    }

    protected function displayException(ExceptionEvent $event)
    {
        /** @var \Symfony\Component\HttpKernel\Exception\HttpException $exception */
        $exception = $event->getThrowable();
        $event->setResponse(
            new Response(
                $exception->getMessage(),
                $exception->getStatusCode(),
                $exception->getHeaders()
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => ["checkHttpException", 128],
        ];
    }
}
