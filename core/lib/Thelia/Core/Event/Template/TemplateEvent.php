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
namespace Thelia\Core\Event\Template;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Template;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\TemplateEvent
 */
class TemplateEvent extends ActionEvent
{
    public function __construct(protected ?Template $template = null)
    {
    }

    public function hasTemplate(): bool
    {
        return $this->template instanceof Template;
    }

    /**
     * @return Template
     */
    public function getTemplate(): ?Template
    {
        return $this->template;
    }

    /**
     * @param Template $template
     *
     * @return $this
     */
    public function setTemplate(?Template $template): static
    {
        $this->template = $template;

        return $this;
    }
}
