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
use Thelia\Model\Lang;

/**
 * Class LangEvent
 * @package Thelia\Core\Event\Lang
 * @author Manuel Raynaud <manu@raynaud.io>
 * @deprecated since 2.4, please use \Thelia\Model\Event\LangEvent
 */
class LangEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Lang
     */
    protected $lang;

    public function __construct(Lang $lang = null)
    {
        $this->lang = $lang;
    }

    /**
     */
    public function setLang(Lang $lang)
    {
        $this->lang = $lang;
    }

    /**
     * @return \Thelia\Model\Lang
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     *
     * check if lang object is present
     *
     * @return bool
     */
    public function hasLang()
    {
        return null !== $this->lang;
    }
}
