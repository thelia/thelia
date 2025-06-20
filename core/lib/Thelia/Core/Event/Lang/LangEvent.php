<?php

declare(strict_types=1);

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
 * Class LangEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 * @deprecated since 2.4, please use \Thelia\Model\Event\LangEvent
 */
class LangEvent extends ActionEvent
{
    public function __construct(protected ?Lang $lang = null)
    {
    }

    public function setLang(Lang $lang): void
    {
        $this->lang = $lang;
    }

    /**
     * @return Lang
     */
    public function getLang(): ?Lang
    {
        return $this->lang;
    }

    /**
     * check if lang object is present.
     */
    public function hasLang(): bool
    {
        return $this->lang instanceof Lang;
    }
}
