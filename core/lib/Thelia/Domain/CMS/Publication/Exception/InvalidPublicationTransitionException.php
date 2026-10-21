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

namespace Thelia\Domain\CMS\Publication\Exception;

use Thelia\Domain\CMS\Publication\PublicationAction;
use Thelia\Domain\CMS\Publication\PublicationState;

/**
 * The gesture does not apply to the state the row is in: scheduling the withdrawal of
 * a row that is not online, for instance.
 */
final class InvalidPublicationTransitionException extends PublicationException
{
    public static function of(PublicationAction $action, PublicationState $state): self
    {
        return new self(self::translated('The "%action" gesture does not apply to a %state row.', ['%action' => $action->value, '%state' => $state->value]));
    }
}
