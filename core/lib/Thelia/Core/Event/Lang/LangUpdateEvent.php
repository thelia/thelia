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

/**
 * Class LangUpdateEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangUpdateEvent extends LangCreateEvent
{
    /**
     * @param int $id
     */
    public function __construct(protected $id)
    {
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setId($id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
