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

namespace Thelia\Domain\CMS\Content\Exception;

class ContentNotFoundException extends \DomainException
{
    public static function withId(int $contentId): self
    {
        return new self(\sprintf('Content with ID %d not found', $contentId));
    }
}
