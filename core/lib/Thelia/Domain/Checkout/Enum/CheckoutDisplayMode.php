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

namespace Thelia\Domain\Checkout\Enum;

/**
 * How the theme lays the checkout out: one screen per step, or the whole tunnel on a
 * single page. It says nothing about which steps there are — that is the `checkout_step`
 * table — only about how the theme is asked to show them.
 */
enum CheckoutDisplayMode: string
{
    /** One screen per step, which is the checkout a shop already has. */
    case Steps = 'steps';

    /** Every step on the same page, laid out one under the other. */
    case OnePage = 'one_page';

    /**
     * The mode a stored value names, falling back to the layout a shop already has.
     *
     * No case is named "0", and none should be: "0" is the value the shop's settings,
     * its forms and its templates all read as "off", and a layout that answers false to
     * half the code reading it is a bug waiting for whoever adds the third mode. The
     * unit test holds that line — this is a guard rail, not a bug being worked around.
     */
    public static function fromStoredValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Steps;
    }
}
