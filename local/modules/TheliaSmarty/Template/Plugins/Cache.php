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

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Customer;
use Thelia\TaxEngine\TaxEngine;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

/**
 * Class Cache
 * @package Thelia\Core\Template\Smarty\Plugins
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class Cache extends AbstractSmartyPlugin
{
    /** @var AdapterInterface */
    protected $adapter;

    /** @var RequestStack */
    protected $requestStack;

    /** @var bool */
    protected $debug;

    /** @var TaxEngine */
    protected $taxEngine;

    /**
     * Cache constructor.
     * @param $kernelDebug
     */
    public function __construct(AdapterInterface $esiFragmentRenderer, RequestStack $requestStack, TaxEngine $taxEngine, $kernelDebug)
    {
        $this->adapter = $esiFragmentRenderer;
        $this->requestStack = $requestStack;
        $this->taxEngine = $taxEngine;
        $this->debug = $kernelDebug;
    }

    public function cache(array $params, $content, $template, &$repeat)
    {
        $key = $this->getParam($params, 'key');
        if (null === $key || empty($key)) {
            throw new \InvalidArgumentException(
                "Missing 'key' parameter in cache arguments"
            );
        }

        $ttl = (int) $this->getParam($params, 'ttl');
        if (null === $ttl) {
            throw new \InvalidArgumentException(
                "Missing 'ttl' parameter in cache arguments"
            );
        }

        if ($this->debug || $ttl < 1) {
            if (null !== $content) {
                $repeat = false;
                return $content;
            }
            return null;
        }

        /** @var CacheItemInterface $cacheItem */
        $cacheItem = $this->adapter->getItem(
            $this->generateKey($params)
        );

        if ($cacheItem->isHit()) {
            $repeat = false;
            return $cacheItem->get();
        }

        if ($content !== null) {
            $cacheItem
                ->expiresAfter((int) $params['ttl'])
                ->set($content);

            $this->adapter->save($cacheItem);
            $repeat = false;
            return $cacheItem->get();
        }
    }

    /**
     * @return string
     */
    protected function generateKey(array $params)
    {
        /** @var Session $session */
        if (null !== $session = $this->requestStack->getCurrentRequest()->getSession()) {
            if (!isset($params['lang'])) {
                $params['lang'] = $session->getLang(true)->getId();
            }
            if (!isset($params['currency'])) {
                $params['currency'] = $session->getCurrency(true)->getId();
            }

            if (!isset($params['country'])) {
                $params['country'] = $this->taxEngine->getDeliveryCountry()->getId();
            }

            if (!isset($params['customer_discount'])) {
                /** @var Customer $customer */
                if (null !== $customer = $session->getCustomerUser()) {
                    $params['customer_discount'] = $customer->getDiscount();
                } else {
                    $params['customer_discount'] = 0;
                }
            }
        }

        return 'smarty_cache_' . md5(json_encode($params));
    }

    /**
     * @return array an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('block', 'cache', $this, 'cache')
        ];
    }
}
