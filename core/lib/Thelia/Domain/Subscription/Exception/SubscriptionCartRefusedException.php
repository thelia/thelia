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

namespace Thelia\Domain\Subscription\Exception;

use Thelia\Core\Translation\Translator;
use Thelia\Domain\Cart\Exception\InvalidCartException;

/**
 * A subscription line the cart refuses: a subscription is bought alone, by a customer who is
 * signed in, on a plan the product offers. The message is translated for the buyer; the
 * front API answers it with a 422, as it does every cart refusal.
 */
final class SubscriptionCartRefusedException extends InvalidCartException
{
    public static function mixedWithOtherLines(): self
    {
        return new self(Translator::getInstance()->trans('A subscription is bought on its own: empty your cart or order it first.'));
    }

    public static function otherLineInASubscriptionCart(): self
    {
        return new self(Translator::getInstance()->trans('Your cart holds a subscription, which is bought on its own: order it first.'));
    }

    public static function planNotOfTheProduct(): self
    {
        return new self(Translator::getInstance()->trans('This subscription plan does not belong to this product.'));
    }

    public static function planNotAvailable(): self
    {
        return new self(Translator::getInstance()->trans('This subscription plan is not available.'));
    }

    public static function customerRequired(): self
    {
        return new self(Translator::getInstance()->trans('Sign in to subscribe: a subscription needs an account.'));
    }
}
