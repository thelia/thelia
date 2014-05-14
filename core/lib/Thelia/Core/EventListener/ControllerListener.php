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
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Controller\Api\BaseApiController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Exception\AdminAccessDenied;
use Thelia\Model\ApiQuery;


/**
 * Class ControllerListener
 * @package Thelia\Core\EventListener
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ControllerListener implements EventSubscriberInterface
{

    protected $securityContext;

    public function __construct(SecurityContext $securityContext)
    {
        $this->securityContext = $securityContext;
    }

    public function adminFirewall(FilterControllerEvent $event)
    {
        $controller = $event->getController();
        //check if an admin is logged in
        if ($controller[0] instanceof BaseAdminController) {

            if (false === $this->securityContext->hasAdminUser() && $event->getRequest()->attributes->get('not-logged') != 1) {
                throw new AdminAccessDenied();
            }
        }
    }

    public function apiFirewall(FilterControllerEvent $event)
    {
        $controller = $event->getController();

        if($controller[0] instanceof BaseApiController) {
            $this->checkApiAccess(
                $event->getRequest()
            );
        }
    }

    private function checkApiAccess(Request $request)
    {

        $key = $request->headers->get('authorization');
        if (null !== $key) {
            $key = substr($key, 6);
        }

        $apiAccount = ApiQuery::create()->findOneByApiKey($key);

        if (null === $apiAccount) {
            throw new UnauthorizedHttpException('Token');
        }

        $secureKey = pack('H*', $apiAccount->getSecureKey());

        $sign = hash_hmac('sha1', $request->getContent(), $secureKey);

        if ($sign != $request->query->get('sign')) {
            throw new PreconditionFailedHttpException('wrong body request signature');
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
            KernelEvents::CONTROLLER => [
                ['adminFirewall', 128],
                ['apiFirewall', 128]
            ]
        ];
    }
}