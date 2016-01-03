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

/**
 * HookRenderEvent is used by the hook template engine plugin function.
 *
 * Class HookRenderEvent
 * @package Thelia\Core\Event\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookRenderEvent extends BaseHookRenderEvent
{
    /** @var  array $fragments an array of fragments collected during the event dispatch */
    protected $fragments;

    public function __construct($code, array $arguments = array())
    {
        parent::__construct($code, $arguments);
        $this->fragments = array();
    }

    /**
     * Add a new fragment
     *
     * @param  string $content
     * @return $this
     */
    public function add($content)
    {
        $this->fragments[] = $content;

        return $this;
    }

    /**
     * Get an array of all the fragments
     *
     * @return array
     */
    public function get()
    {
        return $this->fragments;
    }

    /**
     * Concatenates all fragments in a string.
     *
     * @param  string $glue   the glue between fragments
     * @param  string $before the text before the concatenated string
     * @param  string $after  the text after the concatenated string
     * @return string the concatenate string
     */
    public function dump($glue = '', $before = '', $after = '')
    {
        $ret = '';
        if (0 !== count($this->fragments)) {
            $ret = $before . implode($glue, $this->fragments) . $after;
        }

        return $ret;
    }
}
