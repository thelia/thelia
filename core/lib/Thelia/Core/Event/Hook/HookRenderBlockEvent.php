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

    /** @var array fields that can be added, if empty array any fields can be added */
    protected $fields = [];

    public function __construct($code, array $arguments = [], array $fields = [])
    {
        parent::__construct($code, $arguments);
        $this->fragmentBag = new FragmentBag();
        $this->fields = $fields;
    }

    /**
     * Add a new fragment as an array
     *
     * @param  array $data
     * @return $this
     */
    public function add($data)
    {
        $fragment = new Fragment($data);

        $this->addFragment($fragment);

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
        if (!empty($this->fields)) {
            $fragment->filter($this->fields);
        }
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

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param array $fields
     */
    public function setFields(array $fields)
    {
        $this->fields = $fields;
    }
}
