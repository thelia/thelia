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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Template\ParserInterface;
use Thelia\Exception\AdminAccessDenied;
use Thelia\Model\ConfigQuery;
use Thelia\Core\Template\TemplateHelper;
use Symfony\Component\HttpKernel\Exception\HttpException as BaseHttpException;

/**
 *
 * Class HttpException
 * @package Thelia\Action
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 * @author Manuel Raynaud  <manu@thelia.net>
 */
class HttpException extends BaseAction implements EventSubscriberInterface
{
    /**
     * @var ParserInterface
     */
    protected $parser;

    public function __construct(ParserInterface $parser)
    {
        $this->parser = $parser;
    }

    public function checkHttpException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
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

    protected function displayAdminGeneralError(GetResponseForExceptionEvent $event)
    {
        // Define the template thant shoud be used
        $this->parser->setTemplateDefinition(TemplateHelper::getInstance()->getActiveAdminTemplate());

        $message = $event->getException()->getMessage();

        $response = Response::create(
            $this->parser->render(
                'general_error.html',
                array(
                    "error_message" => $message
                )
            ),
            403
        );

        $event->setResponse($response);
    }

    protected function display404(GetResponseForExceptionEvent $event)
    {
        // Define the template thant shoud be used
        $this->parser->setTemplateDefinition(TemplateHelper::getInstance()->getActiveFrontTemplate());

        $response = new Response($this->parser->render(ConfigQuery::getPageNotFoundView()), 404);

        $event->setResponse($response);
    }

    protected function displayException(GetResponseForExceptionEvent $event)
    {
        /** @var \Symfony\Component\HttpKernel\Exception\HttpException $exception */
        $exception = $event->getException();
        $event->setResponse(new Response($exception->getMessage(), $exception->getStatusCode(), $exception->getHeaders()));
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
            KernelEvents::EXCEPTION => array("checkHttpException", 128),
        );
    }
}
