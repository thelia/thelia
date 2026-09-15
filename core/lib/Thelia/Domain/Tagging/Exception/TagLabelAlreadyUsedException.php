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

namespace Thelia\Domain\Tagging\Exception;

/**
 * Another tag already carries the label that was asked for.
 *
 * Both labels are carried rather than only a sentence, for two reasons. Under
 * utf8mb4_general_ci the collision lands on a spelling that does not look like
 * what was typed — "Salón" collides with "Salon" — so a screen has to be able to
 * name the tag standing in the way. And the two are sometimes the same string,
 * where naming it twice reads as nonsense, so the screen needs to tell the cases
 * apart rather than print one sentence for both.
 *
 * The message is English on purpose: it is what reaches the log. A screen builds
 * its own translated sentence from the two labels.
 */
final class TagLabelAlreadyUsedException extends \InvalidArgumentException
{
    public function __construct(
        public readonly string $requestedLabel,
        public readonly string $existingLabel,
    ) {
        parent::__construct(\sprintf(
            'The label "%s" is already carried by the tag "%s".',
            $requestedLabel,
            $existingLabel,
        ));
    }

    /**
     * Whether the collision is on the very same spelling, rather than on one the
     * collation folds onto it.
     */
    public function isSameSpelling(): bool
    {
        return $this->requestedLabel === $this->existingLabel;
    }
}
