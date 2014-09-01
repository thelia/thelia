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

use Thelia\Core\Hook\Fragment;
use Thelia\Core\Hook\FragmentBag;

/**
 * Class HookRenderBlockEvent
 * @package Thelia\Core\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookRenderBlockEvent extends BaseHookRenderEvent
{

    /** @var  FragmentBag $fragmentBag */
    protected $fragmentBag;

    public function __construct($code, array $arguments = array())
    {
        parent::__construct($code, $arguments);
        $this->fragmentBag = new FragmentBag();
    }

    /**
     * Add a new fragment as an array
     *
     * @param  array $data
     * @return $this
     */
    public function add($data)
    {
        $this->fragmentBag->add($data);

        return $this;
    }

    /**
     * Add a new fragment
     *
     * @param  \Thelia\Core\Hook\Fragment $fragment
     * @return $this
     */
    public function addFragment(Fragment $fragment)
    {
        $this->fragmentBag->addFragment($fragment);

        return $this;
    }

    /**
     * Get all contents
     *
     * @return FragmentBag
     */
    public function get()
    {
        return $this->fragmentBag;
    }

}
