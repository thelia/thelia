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
 * Interface FragmentBagInterface
 * @package Thelia\Core\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
interface FragmentBagInterface
{
    /**
     * Clears all parameters.
     *
     * @api
     */
    public function clear();

    /**
     * Gets the all fragments.
     *
     * @return array An array of parameters
     *
     * @api
     */
    public function all();

    /**
     * Adds a new fragment.
     *
     * @param $content The new fragment content
     * @param $key    The name/key of the fragment
     *
     * @api
     */
    public function add($key, $content);

    /**
     * Gets an array of fragments corresponding.
     *
     * @param string $key The parameter name
     *
     * @return array of fragment or an empty array
     *
     * @api
     */
    public function get($key);

    /**
     * Returns true if al less one fragment with this name is defined.
     *
     * @param string $key The fragment name
     *
     * @return Boolean true if a fragment is defined, false otherwise
     *
     * @api
     */
    public function has($key);

}
