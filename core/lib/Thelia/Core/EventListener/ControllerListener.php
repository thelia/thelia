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
 * @author Manuel Raynaud <manu@raynaud.io>
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

        if ($controller[0] instanceof BaseApiController && $event->getRequest()->attributes->get('not-logged') != 1) {
            $apiAccount = $this->checkApiAccess(
                $event->getRequest()
            );

            $controller[0]->setApiUser($apiAccount);
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

        return $apiAccount;
    }

    /**
     * {@inheritdoc}
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
