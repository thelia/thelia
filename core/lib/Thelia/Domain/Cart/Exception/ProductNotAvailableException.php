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

namespace Thelia\Domain\Cart\Exception;

use Thelia\Core\Translation\Translator;

/**
 * The product a line is added for is not one the current visitor may see: a customer group
 * restricted to some categories, a rule of a module, a private drop. Answered like a product
 * that does not exist, with a neutral sentence: the catalog hides it, the cart does not
 * explain why.
 *
 * An InvalidCartException, so that every caller already answering a refusal of the cart
 * (the front API with a 422, the live components of the theme with their notice) answers
 * this one the same way.
 */
final class ProductNotAvailableException extends InvalidCartException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(Translator::getInstance()->trans('This product is not available.'), 0, $previous);
    }
}
