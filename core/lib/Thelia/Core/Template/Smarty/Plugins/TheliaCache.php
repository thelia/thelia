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


namespace Thelia\Core\Template\Smarty\Plugins;

use Thelia\Cache\Driver\CacheDriverInterface;
use Thelia\Cache\TCacheSupportTrait;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use Thelia\Core\Translation\Translator;


/**
 * Class TheliaCache
 *
 * @package Thelia\Core\Template\Smarty\Plugins
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class TheliaCache extends AbstractSmartyPlugin
{
    use TCacheSupportTrait;

    /** @var bool application debug mode */
    protected $debug;

    /** @var Request $request */
    protected $request;

    /** @var CacheDriverInterface $cache */
    protected $cache;

    /** @var  Translator $translator */
    protected $translator;

    /** @var string $cacheKey */
    protected $cacheKey = null;

    public function __construct(
        $debug,
        Request $request,
        CacheDriverInterface $cache,
        Translator $translator
    ) {
        $this->debug      = $debug;
        $this->request    = $request;
        $this->cache      = $cache;
        $this->translator = $translator;
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

        $key  = $this->getParam($params, 'key');

        if (null == $key) {
            throw new \InvalidArgumentException(
                $this->translator->trans("Missing 'key' parameter in cache arguments")
            );
        }

        if (null === $this->cacheKey) {
            $this->cacheKey = $this->generateKey($params);
        }

        if (null === $content) {
            // first call - return cache if it exists
            if ($this->hasCache($this->cacheKey)) {
                $repeat = false;
                return $this->getCache($this->cacheKey);
            }
        } else {
            // last call - save cache
            $ttl  = $this->getParam($params, 'ttl');
            $rel  = $this->getParam($params, 'rel');
            $references = $this->getReferences($rel);

            $this->setCache(
                $this->cacheKey,
                $content,
                $references,
                $ttl
            );
            $repeat = false;
            return $content;
        }
    }

    protected function generateKey($params)
    {
        $keyParams = array_diff_key(
            $params,
            ['ttl' => null, 'rel' => null]
        );

        $key = [];

        ksort($keyParams);

        foreach ($keyParams as $key => $value) {
            $key[] = $key . $value;
        }

        return md5(implode('.', $key));
    }

    protected function getReferences($rel)
    {
        $references = [];

        if (null !== $rel){
            $references = explode(self::REL_DELIMITER, $rel);
        }

        return $references;
    }

    /**
     * @inheritdoc
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('block', 'cache', $this, 'cache'),
        ];
    }

} 