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

use ApiPlatform\Metadata\Exception\InvalidArgumentException as ApiPlatformInvalidArgumentException;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Thelia\Core\TheliaKernel;
use Thelia\Domain\Payment\Exception\CancelledOrderPaymentRefusedException;
use Thelia\Domain\Payment\Exception\CustomerPaymentMethodException;
use Thelia\Domain\Payment\Exception\GuestCannotStorePaymentMethodException;
use Thelia\Domain\Payment\Exception\InstantPaymentRefusedException;
use Thelia\Domain\Payment\Exception\PaymentCompositionRefusedException;
use Thelia\Domain\Payment\Exception\StoredMethodPaymentRateLimitedException;
use Thelia\Domain\Payment\Exception\StoredMethodRefusedException;

return static function (ContainerConfigurator $container): void {
    $container->extension('api_platform', [
        'title' => 'Thelia API',
        'version' => TheliaKernel::THELIA_VERSION,
        'show_webby' => false,
        'serializer' => [
            'hydra_prefix' => true,
        ],
        // Setting this key replaces the bundle's own exception_to_status, it does
        // not merge with it: of its three defaults, the two that apply here are
        // reintroduced explicitly below. The third maps Doctrine's
        // OptimisticLockException to 409, and Doctrine ORM is not installed.
        // Subclasses are listed before the parent they share, since a caller that
        // needs a status different from the parent's names it first.
        'exception_to_status' => [
            SerializerExceptionInterface::class => 400,
            ApiPlatformInvalidArgumentException::class => 400,

            StoredMethodRefusedException::class => 409,
            // A payment the core refuses to make up (instant means that leave a balance to a
            // module that cannot charge it): a state of the cart, not a malformed request.
            // Before any parent class — it is a CheckoutException, which maps to nothing.
            PaymentCompositionRefusedException::class => 409,
            // An instant means refused to settle its part at payment: the order stands unpaid,
            // nothing was charged, and the buyer may try again or pay otherwise.
            InstantPaymentRefusedException::class => 409,
            // A payment attempted on an order that is cancelled: its parts were given back,
            // nothing was settled nor charged, and the buyer pays a new order instead.
            CancelledOrderPaymentRefusedException::class => 409,
            // PaymentMethodNotOwnedException is deliberately left off this map: the
            // account API (CustomerPaymentMethodOwnershipExtension) and the checkout
            // selection endpoint both throw it for "not this customer's method", and
            // want two different answers (404 there, 422 here — see
            // CheckoutStoredPaymentMethodApiTest). Each gets its own by never letting
            // the exception reach this listener: the account API scopes its queries so
            // a foreign id is never found in the first place, and it falls through to
            // CustomerPaymentMethodException::class below wherever it is thrown anyway.
            GuestCannotStorePaymentMethodException::class => 403,
            StoredMethodPaymentRateLimitedException::class => 429,
            CustomerPaymentMethodException::class => 422,
        ],
        'defaults' => [
            'pagination_client_items_per_page' => true,
            // The caller picks its page size, so the page size needs a ceiling:
            // without one a single call can ask the shop to load, hydrate and
            // serialize a whole table in one response. A hundred is well above
            // what the shipped themes ask for (thirty on the front, twenty-five
            // in the back-office) and above the page sizes an integration
            // usually walks a catalogue with. A project that needs a different
            // ceiling overrides this key in its own api_platform configuration,
            // and an operation that needs its own says so on its metadata.
            'pagination_maximum_items_per_page' => 100,
            'stateless' => false,
        ],
        'mapping' => [
            'paths' => [],
        ],
        'formats' => [
            'json' => [
                'mime_types' => ['application/json'],
            ],
            'jsonld' => [
                'mime_types' => ['application/ld+json'],
            ],
            'html' => [
                'mime_types' => ['text/html'],
            ],
        ],
        'swagger' => [
            'versions' => [3],
            'swagger_ui_extra_configuration' => [
                'docExpansion' => 'none',
                'filter' => true,
                'persistAuthorization' => true,
                'showCommonExtensions' => true,
            ],
            'api_keys' => [
                'JWT' => [
                    'name' => 'Authorization',
                    'type' => 'header',
                ],
            ],
        ],
    ], prepend: true);
};
