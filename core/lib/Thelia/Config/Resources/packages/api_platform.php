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
use Thelia\Domain\Catalog\Bundle\Exception\BundleDefinitionException;
use Thelia\Domain\Catalog\Bundle\Exception\BundleWriteConflictException;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Domain\Order\Exception\OrderStatusChangeConflictException;
use Thelia\Domain\Payment\Exception\CancelledOrderPaymentRefusedException;
use Thelia\Domain\Payment\Exception\CustomerPaymentMethodException;
use Thelia\Domain\Payment\Exception\GuestCannotStorePaymentMethodException;
use Thelia\Domain\Payment\Exception\InstantPaymentRefusedException;
use Thelia\Domain\Payment\Exception\PaymentCompositionRefusedException;
use Thelia\Domain\Payment\Exception\StoredMethodPaymentRateLimitedException;
use Thelia\Domain\Payment\Exception\StoredMethodRefusedException;
use Thelia\Domain\Quote\Cart\QuoteCartLockedException;
use Thelia\Domain\Quote\Exception\QuoteAlreadyOrderedException;
use Thelia\Domain\Quote\Exception\QuoteCurrencyUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteException;
use Thelia\Domain\Quote\Exception\QuoteLinesUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Domain\Quote\Exception\UnknownQuoteException;
use Thelia\Domain\Subscription\Exception\SubscriptionActionRefusedException;
use Thelia\Domain\Subscription\Exception\SubscriptionException;
use Thelia\Domain\Subscription\Exception\SubscriptionTransitionRefusedException;

return static function (ContainerConfigurator $container): void {
    $container->extension('api_platform', [
        'title' => 'Thelia API',
        'version' => TheliaKernel::THELIA_VERSION,
        'show_webby' => false,
        // The operation of a request is put on it by API Platform's Symfony listeners: with
        // this off, the upload and cart controllers and the admin permission check never see it.
        // Set here, a project keeps the last word: its own api_platform configuration is
        // merged over this prepended default.
        'use_symfony_listeners' => true,
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
            // A bundle refused by the model whoever writes it: a second sale element for a bundle,
            // a cart line whose composition does not fit its product. The front operations catch
            // them and answer on their own; the admin ones let them through.
            BundleDefinitionException::class => 422,
            BundleWriteConflictException::class => 409,
            // A change of status the database broke a lock conflict of (deadlock): nothing was
            // written, asked again it is judged on what the other request left.
            OrderStatusChangeConflictException::class => 409,
            InvalidBundleCompositionException::class => 422,
            // What the state of a subscription does not allow (cancel a cancelled one, pause one that
            // is not pausable): a state of the resource, not a malformed request.
            SubscriptionActionRefusedException::class => 409,
            SubscriptionTransitionRefusedException::class => 409,
            SubscriptionException::class => 422,
            // A quote that does not exist, or that the caller may not read, is not found. What the
            // state of a quote does not allow (answer one that is not waiting, change a cart
            // whose prices are settled, order a quote twice) is a state of the resource, not a
            // malformed request; any other refusal of the quote facade (an invalid line, a revision
            // that is no longer the current one, a line the shop no longer sells) is a request the
            // shop cannot take. The reason of the refusal is in the body (QuoteProblemNormalizer).
            // Each subclass is named before QuoteException, the parent they share.
            UnknownQuoteException::class => 404,
            QuoteTransitionRefusedException::class => 409,
            QuoteCartLockedException::class => 409,
            QuoteAlreadyOrderedException::class => 409,
            QuoteLinesUnavailableException::class => 422,
            QuoteCurrencyUnavailableException::class => 422,
            QuoteException::class => 422,
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
