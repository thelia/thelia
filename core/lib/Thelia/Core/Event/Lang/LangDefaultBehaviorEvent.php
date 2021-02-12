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

namespace Thelia\Core\Event\Lang;

use Thelia\Core\Event\ActionEvent;

/**
 * Class LangDefaultBehaviorEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangDefaultBehaviorEvent extends ActionEvent
{
    /**
     * @var int default behavior status
     */
    protected $defaultBehavior;

    public function __construct($defaultBehavior)
    {
        $this->defaultBehavior = $defaultBehavior;
    }

    /**
     * @param int $defaultBehavior
     */
    public function setDefaultBehavior($defaultBehavior)
    {
        $this->defaultBehavior = $defaultBehavior;
    }

    /**
     * @return int
     */
    public function getDefaultBehavior()
    {
        return $this->defaultBehavior;
    }
}
