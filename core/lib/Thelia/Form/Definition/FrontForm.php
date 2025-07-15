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

namespace Thelia\Form\Definition;

/**
 * Class FrontForm.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
final class FrontForm
{
    public const ADDRESS_CREATE = 'thelia.front.address.create';
    public const ADDRESS_UPDATE = 'thelia.front.address.update';
    public const CART_ADD = 'thelia.cart.add';
    public const CONTACT = 'thelia.front.contact';
    public const COUPON_CONSUME = 'thelia.order.coupon';
    public const CUSTOMER_LOGIN = 'thelia.front.customer.login';
    public const CUSTOMER_LOST_PASSWORD = 'thelia.front.customer.lostpassword';
    public const CUSTOMER_CREATE = 'thelia.front.customer.create';
    public const CUSTOMER_PROFILE_UPDATE = 'thelia.front.customer.profile.update';
    public const CUSTOMER_PASSWORD_UPDATE = 'thelia.front.customer.password.update';
    public const NEWSLETTER = 'thelia.front.newsletter';
    public const NEWSLETTER_UNSUBSCRIBE = 'thelia.front.newsletter.unsubscribe';
    public const ORDER_DELIVER = 'thelia.order.delivery';
    public const ORDER_PAYMENT = 'thelia.order.payment';
}
