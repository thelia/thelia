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


namespace Thelia\Core\Template\Element;

use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Log\Tlog;
use Thelia\Type\TypeCollection;

/**
 * Add Cache functionality for loop.
 *
 * @package Thelia\Core\Template\Smarty\Plugins
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 *
 * @property Request $request
 * @property ArgumentCollection $args
 */
trait LoopCacheTrait
{
    /** @var bool Use the CacheFactory feature */
    protected $isCacheable = false;

    /** @var string The cache is dependant of the currency */
    protected $useCacheByCurrency = false;

    /** @var string The cache is dependant of the user role */
    protected $useCacheByUser = false;

    /** @var string The cache is dependant of the lang */
    protected $useCacheByLang = false;

    /** @var string The related attribute */
    protected $cacheAttribute = 'id';

    /**
     * Check the attributes of the loop to know if we can cache the loop
     * or not. If the loop is not cacheable it returns `null`. Else it returns
     * an unique key related to the loop attributes.
     *
     * By default, `null` is always returned. To activate it, the loop class should set
     * to `true` the isCacheable attribute or overrides getCacheKey method.
     *
     * @return null|string
     */
    public function getCacheKey()
    {
        // check if we can cache this loop
        if (!$this->isCacheable) {
            return null;
        }

        if ($this->args->hasKey("nocache")) {
            return null;
        }

        // check if we can cache this loop
        if (!$this->isCacheable()) {
            return null;
        }

        $hash = $this->args->getHash();

        // add language
        if ($this->useCacheByLang || ($this instanceof BaseI18nLoop)) {
            if (!$this->args->hasKey("lang")) {
                $hash .= '.' . $this->request->getSession()->getLang()->getLocale();
            }
        }

        // add currency constraint
        if ($this->useCacheByCurrency) {
            $hash .= '.' . $this->request->getSession()->getCurrency()->getId();
        }

        // add role constraint
        if ($this->useCacheByUser) {
            if (null !== $customer = $this->request->getSession()->getCustomerUser()) {
                $hash .= '.' . $customer;
            }
        }

        return $hash;
    }

    /**
     * Check loop attributes to see if it is cacheable.
     *
     * The cache is used for loop that return an unique element and not
     * a collection.
     *
     * By default, it uses the `id` attribute but you can tweak this using
     * the `cacheAttribute` or by overriding isCacheable method
     *
     * @return bool
     */
    protected function isCacheable()
    {
        if ($this->args->hasKey($this->cacheAttribute)) {
            $arg = $this->args->get($this->cacheAttribute);
            if ($arg instanceof TypeCollection) {
                return ($arg->getCount() === 1);
            } else {
                return (null !== $arg->getValue());
            }
        }

        return false;
    }

    /**
     * Return an array of references to this cache. This references are used to delete
     * cache entries that are no more up to time.
     *
     * For instance, when an object is saved, an event will be trigger to delete cache related
     * to this object.
     *
     * @return array|string|null  a reference or an array of references related to this cache
     */
    abstract public function getCacheRef();
}
