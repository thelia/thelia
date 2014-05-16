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

namespace Thelia\Core\Event\Hook;

use Symfony\Component\EventDispatcher\Event;
use Thelia\Core\Hook\FragmentBag;
use Thelia\Core\Hook\FragmentBagInterface;

/**
 * Class HookRenderBlockEvent
 * @package Thelia\Core\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookRenderBlockEvent extends BaseHookRenderEvent
{
    const DEFAULT_KEY = "content";

    /** @var  FragmentBagInterface $fragments */
    protected $fragmentBag;

    public function __construct($code, array $arguments = array())
    {
        parent::__construct($code, $arguments);
        $this->fragmentBag = new FragmentBag();
    }

    /**
     *
     * @param  string $content
     * @parma string $key
     * @return $this
     */
    public function add($content, $key=self::DEFAULT_KEY)
    {
        $this->fragmentBag->add($key, $content);

        return $this;
    }

    /**
     * @return array
     */
    public function keys()
    {
        return $this->fragmentBag->keys();
    }

    /**
     * @param  string $key
     * @return array
     */
    public function get($key=self::DEFAULT_KEY)
    {
        return $this->fragmentBag->get($key);
    }

    /**
     * @param  string $key
     * @param  string $glue
     * @param  string $before
     * @param  string $after
     * @return string
     */
    public function dump($key=self::DEFAULT_KEY, $glue='', $before='', $after='')
    {
        $ret = '';
        $fragments = $this->get($key);
        if (0 !== count($fragments)) {
            $ret = $before . implode($glue, $fragments) . $after;
        }

        return $ret;
    }

}
