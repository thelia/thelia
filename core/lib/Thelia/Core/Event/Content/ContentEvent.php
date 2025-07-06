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

namespace Thelia\Core\Event\Content;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Content;

/**
 * Class ContentEvent.
 *
 * @author manuel raynaud <manu@raynaud.io>
 *
 * @deprecated since 2.4, please use \Thelia\Model\Event\ContentEvent
 */
class ContentEvent extends ActionEvent
{
    public function __construct(protected ?Content $content = null)
    {
    }

    public function setContent(Content $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): ?Content
    {
        return $this->content;
    }

    /**
     * check if content exists.
     */
    public function hasContent(): bool
    {
        return $this->content instanceof Content;
    }
}
