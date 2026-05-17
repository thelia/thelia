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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Thelia\Form\AddressCreateForm;
use Thelia\Form\AddressUpdateForm;
use Thelia\Form\CartAdd;
use Thelia\Form\ContactForm;
use Thelia\Form\CouponCode;
use Thelia\Form\CustomerCreateForm;
use Thelia\Form\CustomerLogin;
use Thelia\Form\CustomerLostPasswordForm;
use Thelia\Form\CustomerPasswordUpdateForm;
use Thelia\Form\CustomerProfileUpdateForm;
use Thelia\Form\EmptyForm;
use Thelia\Form\NewsletterForm;
use Thelia\Form\NewsletterUnsubscribeForm;
use Thelia\Form\OrderDelivery;
use Thelia\Form\OrderPayment;

return static function (ContainerConfigurator $configurator): void {
    $parameters = $configurator->parameters();

    $parameters->set('Thelia.parser.forms', [
        // Common forms
        'thelia.order.delivery' => OrderDelivery::class,
        'thelia.order.payment' => OrderPayment::class,
        'thelia.cart.add' => CartAdd::class,
        'thelia.order.coupon' => CouponCode::class,
        'thelia.empty' => EmptyForm::class,

        // Frontend forms
        'thelia.front.customer.login' => CustomerLogin::class,
        'thelia.front.customer.lostpassword' => CustomerLostPasswordForm::class,
        'thelia.front.customer.create' => CustomerCreateForm::class,
        'thelia.front.customer.profile.update' => CustomerProfileUpdateForm::class,
        'thelia.front.customer.password.update' => CustomerPasswordUpdateForm::class,
        'thelia.front.address.create' => AddressCreateForm::class,
        'thelia.front.address.update' => AddressUpdateForm::class,
        'thelia.front.contact' => ContactForm::class,
        'thelia.front.newsletter' => NewsletterForm::class,
        'thelia.front.newsletter.unsubscribe' => NewsletterUnsubscribeForm::class,
    ]);
};
