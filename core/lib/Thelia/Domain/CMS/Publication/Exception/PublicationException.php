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

/**
 * A publication gesture the row cannot take. The message is meant for the person who
 * made the gesture: a back office shows it as it is.
 */
class PublicationException extends \DomainException
{
}
