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

namespace Thelia\Domain\Media\Video;

/**
 * The video being saved points at nothing playable: no file the shop stores, and
 * no identifier on a platform.
 *
 * A LogicException, which is the channel the write actions of this core report a
 * refusal through: the API turns it into a 422 carrying the message.
 */
final class IncompleteVideoException extends \LogicException
{
}
