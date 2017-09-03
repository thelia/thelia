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

    /**
     * Cache constructor.
     * @param AdapterInterface $esiFragmentRenderer
     * @param RequestStack $requestStack
     * @param bool $debug
     */
    public function __construct(AdapterInterface $esiFragmentRenderer, RequestStack $requestStack, $debug)
    {
        $this->adapter = $esiFragmentRenderer;
        $this->requestStack = $requestStack;
        $this->debug = $debug;
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
     * @param array $params
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
        }

        return 'smarty_cache_' . md5(json_encode($params));
    }

    /**
     * @return array an array of SmartyPluginDescriptor
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('block', 'cache', $this, 'cache')
        );
    }
}
