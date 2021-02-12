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

namespace Thelia\Form\Lang;

use Thelia\Core\Event\ActionEvent;

/**
 * Class LangUrlEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangUrlEvent extends ActionEvent
{
    protected $url = [];

    public function addUrl($id, $url): void
    {
        $this->url[$id] = $url;
    }

    public function getUrl()
    {
        return $this->url;
    }
}
