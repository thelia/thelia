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

namespace Thelia\Core\Hook;

/**
 * Class FragmentBag
 * @package Thelia\Core\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FragmentBag implements FragmentBagInterface
{
    /** @var array $fragments */
    protected $fragments;

    public function __construct()
    {
        $this->fragments = array();
    }

    /**
     * Clears all parameters.
     *
     * @api
     */
    public function clear()
    {
        $this->fragments = array();
    }

    /**
     * Gets the all fragments.
     *
     * @return array An array of parameters
     *
     * @api
     */
    public function all()
    {
        return $this->fragments;
    }

    /**
     * Gets the all keys fragment.
     *
     * @return array An array of parameters
     *
     * @api
     */
    public function keys()
    {
        return array_keys($this->fragments);
    }

    /**
     * Gets an array of fragments corresponding.
     *
     * @param string $key The parameter key
     *
     * @return array|null Fragments
     *
     * @api
     */
    public function get($key)
    {
        $ret = array();
        /** @var FragmentInterface $fragment */
        if (array_key_exists($key, $this->fragments)) {
            $ret = $this->fragments[$key];
        }

        return $ret;
    }

    /**
     * Adds a new fragment.
     *
     * @api
     */
    public function add($key, $content)
    {
        if (! array_key_exists($key, $this->fragments)) {
            $this->fragments[$key] = array();
        }
        $this->fragments[$key][] = $content;
    }

    /**
     * Returns true if al less one fragment with this key is defined.
     *
     * @param string $key The fragment key
     *
     * @return Boolean true if a fragment is defined, false otherwise
     *
     * @api
     */
    public function has($key)
    {
        return array_key_exists($key, $this->fragments);
    }

}
