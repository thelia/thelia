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

use Doctrine\Common\Cache\FilesystemCache;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\ConfigQuery;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

/**
 * Class Cache
 * @package TheliaSmarty\Template\Plugins
 * @author Julien Chanséaume <julien@thelia.net>
 *
 * A simple module to cache some parts of a template.
 *
 * This example will cache the generate code of a navigation menu. It will generate different caches depending of the
 * current category, the lang and the currency. The cache will be used for 60 seconds, before being deleted :
 *
 * ```smarty
 * {tcache key="navigation" ttl="60" category={category attr="id"}}
 * <div class="navigation">
 * {loop name="category-navigation" type="category" }
 * ....
 * <!-- other expensive loop -->
 * ....
 * {/loop}
 * </div>
 * {/tcache}
 * ```
 *
 * Parameters :
 *
 * - **key** : (mandatory) a unique key
 * - **ttl** : (mandatory) a time to live in seconds
 * - **lang** : specific cache by lang, possible values : *on*, *off* (default: *on*)
 * - **currency** : specific cache by currency, possible values : *on*, *off* (default: *on*)
 * - **customer** : specific cache by customer, possible values : *on*, *off* (default: **off**)
 * - **admin** : specific cache by administrator, possible values : *on*, *off* (default: *off*)
 * - **role** : specific cache by role (none, customer, admin), possible values : *on*, *off* (default: *off*)
 *
 * You can add as many arguments as you need to generate a unique key and by the way prevents collisions
 *
 */
class Cache extends AbstractSmartyPlugin
{

    const CACHE_DIR = 'smarty-fragment';

    /** @var Request $request */
    protected $request;

    protected $cacheDir;

    protected $disabled;

    /** @var FilesystemCache */
    protected $cacheDriver;

    protected $defaultParams = [];

    public function __construct(
        Request $request,
        $cacheDir,
        $debug
    ) {
        $this->request = $request;
        $this->cacheDir = $cacheDir;

        $this->disabled = $debug;
        if (!$this->disabled) {
            $this->disabled = intval(ConfigQuery::read('smarty_cache_disabled', 0)) === 1;
        }
    }

    /**
     * @inheritdoc
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('block', 'tcache', $this, 'cacheBlock'),
        ];
    }

    /**
     * Caches the result for a smarty block.
     *
     * @param $params
     * @param $content
     * @param $template
     * @param $repeat
     */
    public function cacheBlock($params, $content, $template, &$repeat)
    {
        $key = $this->getParam($params, 'key');
        if (null == $key) {
            throw new \InvalidArgumentException(
                "Missing 'key' parameter in tcache arguments"
            );
        }

        $ttl = intval($this->getParam($params, 'ttl'));
        if ($ttl <= 0) {
            throw new \InvalidArgumentException(
                "Invalid 'ttl' parameter in tcache arguments"
            );
        }

        // in 'debug' mode, we skip cache
        if ($this->disabled) {
            if (null !== $content) {
                $repeat = false;
                return $content;
            }
            return '';
        }

        if (null === $this->cacheDriver) {
            $this->cacheDriver = new FilesystemCache($this->getCacheDir());

            $customer = $this->request->getSession()->getCustomerUser();
            $admin = $this->request->getSession()->getAdminUser();

            $this->defaultParams = [
                'lang' => $this->request->getSession()->getLang(true)->getId(),
                'currency' => $this->request->getSession()->getCurrency(true)->getId(),
                'customer' => null !== $customer ? $customer->getId() : '0',
                'admin' => null !== $admin ? $admin->getId() : '0',
                'role' => sprintf('%s%s', null !== $admin ? 'ADMIN' : '', null !== $customer ? 'CUSTOMER' : '')
            ];
        }

        $cacheKey = $this->generateKey($params);

        if (null === $content) {
            $cacheContent = $this->cacheDriver->fetch($cacheKey);

            // first call - return cache if it exists
            if ($cacheContent) {
                $repeat = false;
                return $cacheContent;
            }
        } else {
            $this->cacheDriver->save($cacheKey, $content, $ttl);

            $repeat = false;
            return $content;
        }
    }

    /**
     * get the cache directory for sitemap
     *
     * @return mixed|string
     */
    protected function getCacheDir()
    {
        $cacheDir = $this->cacheDir;
        $cacheDir = rtrim($cacheDir, '/\\');
        $cacheDir .= DS . self::CACHE_DIR . DS;

        return $cacheDir;
    }

    /**
     * Generate a unique key based on parameters
     *
     * @param $params
     * @return string
     */
    protected function generateKey($params)
    {
        $keyParams = array_merge(
            $params,
            [
                'ttl' => null,
                'lang' => $this->getBoolParam($params, 'lang', true) ? $this->defaultParams['lang'] : null,
                'currency' => $this->getBoolParam($params, 'currency', true) ? $this->defaultParams['currency'] : null,
                'customer' => $this->getBoolParam($params, 'customer', false) ? $this->defaultParams['customer'] : null,
                'admin' => $this->getBoolParam($params, 'admin', false) ? $this->defaultParams['admin'] : null,
                'role' => $this->getBoolParam($params, 'role', false) ? $this->defaultParams['role'] : null
            ]
        );

        $key = [];

        ksort($keyParams);

        foreach ($keyParams as $k => $v) {
            if (null !== $v) {
                $key[] = $k . '=' . $v;
            }
        }

        return md5(implode('.', $key));
    }

    protected function getBoolParam($params, $key, $default)
    {
        $return = $this->getParam($params, $key, $default);
        $return = filter_var($return, FILTER_VALIDATE_BOOLEAN);

        if (null === $return) {
            $return = $default;
        }

        return $return;
    }
}
