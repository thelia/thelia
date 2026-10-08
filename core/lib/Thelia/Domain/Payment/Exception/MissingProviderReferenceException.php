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

namespace Thelia\Domain\Payment\Exception;

/**
 * An authorization was reported without the reference the provider gave it. Without it a
 * replayed notification cannot be told from a second authorization, and the amount the
 * shop believes it may capture would double.
 */
final class MissingProviderReferenceException extends PaymentException
{
}
