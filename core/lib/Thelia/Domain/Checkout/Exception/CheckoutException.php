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

namespace Thelia\Domain\Checkout\Exception;

use Thelia\Core\Translation\Translator;

abstract class CheckoutException extends \RuntimeException
{
    /**
     * @param array<string, string> $parameters placeholders of the message, substituted whether or
     *                                          not the shop has a translation for it
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, array $parameters = [])
    {
        $message = Translator::getInstance()->trans($message, $parameters);
        parent::__construct($message, $code, $previous);
    }
}
