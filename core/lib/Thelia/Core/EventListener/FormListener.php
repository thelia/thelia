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
use Thelia\Controller\Admin\CustomerController;
use Thelia\Core\Controller\ControllerResolver;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TheliaFormEvent;
use Thelia\Core\HttpFoundation\Request;

/**
 * Class FormListener
 * @package Thelia\Core\EventListener
 * @author Manuel Raynaud <manu@thelia.net>
 */
class FormListener implements EventSubscriberInterface
{

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var ControllerResolver
     */
    protected $resolver;

    /**
     * @param Request $request
     * @param ControllerResolver $resolver
     */
    public function __construct(Request $request, ControllerResolver $resolver)
    {
        $this->request = $request;
        $this->resolver = $resolver;
    }

    public function removeEmailConfirmation(TheliaFormEvent $event)
    {
        $controller = $this->resolver->getController($this->request);

        if ($controller[0] instanceof CustomerController) {
            $formBuilder = $event->getForm()->getFormBuilder();

            if ($formBuilder->has('email_confirm')) {
                $formBuilder->remove('email_confirm');
            }
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
        return [
            TheliaEvents::FORM_AFTER_BUILD.".thelia_customer_create" => 'removeEmailConfirmation'
        ];
    }
}
