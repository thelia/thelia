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

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\CMS\Publication\PublicationRequest;
use Thelia\Domain\CMS\Publication\PublicationStatus;
use Thelia\Model\Content;

/**
 * Publish, schedule, cancel or take offline a content: `TheliaEvents::CONTENT_UPDATE_PUBLICATION`.
 * The listener writes the row and leaves the state it reached on the event.
 */
#[Exclude]
final class ContentUpdatePublicationEvent extends ContentEvent
{
    private ?PublicationStatus $status = null;

    public function __construct(
        Content $content,
        public readonly PublicationRequest $request,
    ) {
        parent::__construct($content);
    }

    public function getStatus(): ?PublicationStatus
    {
        return $this->status;
    }

    public function setStatus(PublicationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
