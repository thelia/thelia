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

namespace TheliaSmarty\Template\Plugins;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Exception\AuthorizationException;
use TheliaSmarty\Template\SmartyPluginDescriptor;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Exception\OrderException;
use Thelia\Model\AddressQuery;
use Thelia\Model\ModuleQuery;

class Security extends AbstractSmartyPlugin
{
    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /** @var RequestStack */
    protected $requestStack;

    /** @var SecurityContext */
    private $securityContext;

    public function __construct(RequestStack $requestStack, EventDispatcherInterface $dispatcher, SecurityContext $securityContext)
    {
        $this->securityContext = $securityContext;
        $this->requestStack = $requestStack;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Process security check function
     *
     * @param  array                                                   $params
     * @param  \Smarty                                                 $smarty
     * @return string                                                  no text is returned.
     * @throws \Thelia\Core\Security\Exception\AuthenticationException
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function checkAuthFunction($params, &$smarty)
    {
        $roles = $this->explode($this->getParam($params, 'role'));
        $resources = $this->explode($this->getParam($params, 'resource'));
        $modules = $this->explode($this->getParam($params, 'module'));
        $accesses = $this->explode($this->getParam($params, 'access'));

        if (! $this->securityContext->isGranted($roles, $resources, $modules, $accesses)) {
            if (null === $this->securityContext->checkRole($roles)) {
                // The current user is not logged-in.
                $ex = new AuthenticationException(
                    sprintf(
                        "User not granted for roles '%s', to access resources '%s' with %s.",
                        implode(',', $roles),
                        implode(',', $resources),
                        implode(',', $accesses)
                    )
                );

                $loginTpl = $this->getParam($params, 'login_tpl');

                if (null != $loginTpl) {
                    $ex->setLoginTemplate($loginTpl);
                }
            } else {
                // We have a logged-in user, who do not have the proper permission. Issue an AuthorizationException.
                $ex = new AuthorizationException(
                    sprintf(
                        "User not granted for roles '%s', to access resources '%s' with %s.",
                        implode(',', $roles),
                        implode(',', $resources),
                        implode(',', $accesses)
                    )
                );
            }

            throw $ex;
        }

        return '';
    }

    public function checkCartNotEmptyFunction($params, &$smarty)
    {
        $cart = $this->getSession()->getSessionCart($this->dispatcher);
        if ($cart===null || $cart->countCartItems() == 0) {
            throw new OrderException('Cart must not be empty', OrderException::CART_EMPTY, array('empty' => 1));
        }

        return "";
    }

    public function checkValidDeliveryFunction($params, &$smarty)
    {
        $order = $this->getSession()->getOrder();
        /* Does address and module still exists ? We assume address owner can't change neither module type */
        if ($order !== null) {
            $checkAddress = AddressQuery::create()->findPk($order->getChoosenDeliveryAddress());
            $checkModule = ModuleQuery::create()->findPk($order->getDeliveryModuleId());
        } else {
            $checkAddress = $checkModule = null;
        }

        if (null === $order || null == $checkAddress || null === $checkModule) {
            throw new OrderException('Delivery must be defined', OrderException::UNDEFINED_DELIVERY, array('missing' => 1));
        }

        return "";
    }

    /**
     * Define the various smarty plugins handled by this class
     *
     * @return array an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('function', 'check_auth', $this, 'checkAuthFunction'),
            new SmartyPluginDescriptor('function', 'check_cart_not_empty', $this, 'checkCartNotEmptyFunction'),
            new SmartyPluginDescriptor('function', 'check_valid_delivery', $this, 'checkValidDeliveryFunction'),
        );
    }

    /**
     * @return Session
     */
    protected function getSession()
    {
        return $this->requestStack->getCurrentRequest()->getSession();
    }
}
