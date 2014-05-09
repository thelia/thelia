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
use Thelia\Core\Hook\HookFragmentInterface;

/**
 * Class HookEvent
 * @package Thelia\Core\Event\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookEvent extends Event {

    protected $id = null;

    protected $fragments = array();

    /**
     * @param array $fragments
    public function setFragments($fragments)
    {
    $this->fragments = $fragments;
    return $this;
    }
     */

    public function addFragment(HookFragmentInterface $fragment)
    {
        $this->fragments[] = $fragment;
        return $this;
    }

    /**
     * @return array
     */
    public function getFragments()
    {
        return $this->fragments;
    }

    /**
     * @param null $id
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return null
     */
    public function getId()
    {
        return $this->id;
    }

} 