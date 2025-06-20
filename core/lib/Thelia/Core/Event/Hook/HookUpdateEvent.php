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
namespace Thelia\Core\Event\Hook;

use Thelia\Model\Hook;

/**
 * Class HookUpdateEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookUpdateEvent extends HookCreateEvent
{

    protected $by_module;

    protected $block;

    protected $chapo;

    protected $description;

    public function __construct(protected int $hook_id)
    {
    }

    /**
     * @return $this
     */
    public function setHookId(int $hook_id): self
    {
        $this->hook_id = $hook_id;

        return $this;
    }

    /**
     * @return Hook
     */
    public function getHookId(): int
    {
        return $this->hook_id;
    }

    public function setBlock($block): static
    {
        $this->block = $block;

        return $this;
    }

    public function getBlock()
    {
        return $this->block;
    }

    public function setByModule($by_module): static
    {
        $this->by_module = $by_module;

        return $this;
    }

    public function getByModule()
    {
        return $this->by_module;
    }

    public function setChapo($chapo): static
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getChapo()
    {
        return $this->chapo;
    }

    public function setDescription($description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }
}
