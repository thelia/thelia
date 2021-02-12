<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event\Hook;

use Thelia\Core\Hook\Fragment;
use Thelia\Core\Hook\FragmentBag;

/**
 * Class HookRenderBlockEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookRenderBlockEvent extends BaseHookRenderEvent
{
    /** @var FragmentBag $fragmentBag */
    protected $fragmentBag;

    /** @var array fields that can be added, if empty array any fields can be added */
    protected $fields = [];

    public function __construct($code, array $arguments = [], array $fields = [], array $templateVariables = [])
    {
        parent::__construct($code, $arguments, $templateVariables);
        $this->fragmentBag = new FragmentBag();
        $this->fields = $fields;
    }

    /**
     * Add a new fragment as an array.
     *
     * @param array $data
     *
     * @return $this
     */
    public function add($data)
    {
        $fragment = new Fragment($data);

        $this->addFragment($fragment);

        return $this;
    }

    /**
     * Add a new fragment.
     *
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
     * Get all contents.
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

    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }
}
