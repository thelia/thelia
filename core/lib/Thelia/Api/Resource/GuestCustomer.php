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

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Api\State\Processor\GuestCustomerConversionProcessor;
use Thelia\Api\State\Processor\GuestCustomerRegistrationProcessor;

/**
 * Ordering without an account, and coming back from it.
 *
 * A resource of its own rather than another operation on {@see Customer}: what goes in
 * is not a customer (no password, and the address really is required, which the front
 * customer write group does not say), and what comes back is not one either — it is a
 * short-lived token. Folding it into Customer would mean widening the write group two
 * live operations already share, and exposing a credential on the resource that
 * describes an account.
 *
 * Nothing here can say "this is a guest": the flag lives on the customer row and is
 * set by the registration service alone. A body that could claim it would be a body
 * that could opt out of having a password.
 */
#[ApiResource(
    operations: [
        // Deliberately open: this is how a visitor with no account identifies itself,
        // so there is no credential to ask for. It is not unguarded — the processor
        // spends a rate limit before it looks anything up, then asks GuestCheckoutPolicy
        // whether this shop, and this cart, may be ordered without an account at all.
        new Post(
            uriTemplate: '/front/guest-customers',
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            validationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
            processor: GuestCustomerRegistrationProcessor::class,
        ),
        // Also open, and authorized inside the processor rather than here: it accepts
        // either the guest's own token or a valid order tracking token, and a security
        // expression cannot see the second one — it arrives in the body.
        new Post(
            uriTemplate: '/front/guest-customers/{id}/convert',
            denormalizationContext: ['groups' => [self::GROUP_FRONT_CONVERT]],
            validationContext: ['groups' => [self::GROUP_FRONT_CONVERT]],
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
            read: false,
            processor: GuestCustomerConversionProcessor::class,
        ),
    ],
)]
final class GuestCustomer
{
    public const GROUP_FRONT_READ = 'front:guest_customer:read';
    public const GROUP_FRONT_WRITE = 'front:guest_customer:write';
    public const GROUP_FRONT_CONVERT = 'front:guest_customer:convert';

    #[Groups([self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotBlank(groups: [self::GROUP_FRONT_WRITE])]
    #[Email(groups: [self::GROUP_FRONT_WRITE])]
    public ?string $email = null;

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotBlank(groups: [self::GROUP_FRONT_WRITE])]
    public ?string $firstname = null;

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotBlank(groups: [self::GROUP_FRONT_WRITE])]
    public ?string $lastname = null;

    #[Groups([self::GROUP_FRONT_WRITE])]
    public ?int $customerTitleId = null;

    #[Groups([self::GROUP_FRONT_WRITE])]
    public ?int $langId = null;

    /**
     * The password the guest chooses when completing the account. Write only: it is
     * absent from every read group, so it can never come back out.
     */
    #[Groups([self::GROUP_FRONT_CONVERT])]
    #[NotBlank(groups: [self::GROUP_FRONT_CONVERT])]
    #[Length(min: 4, groups: [self::GROUP_FRONT_CONVERT])]
    public ?string $password = null;

    /**
     * An order tracking token, for a guest who came back from an email rather than
     * from the checkout it still holds a token for. Write only, same reason.
     */
    #[Groups([self::GROUP_FRONT_CONVERT])]
    public ?string $orderToken = null;

    /**
     * The guest's credential for the rest of the checkout: a JWT granting ROLE_GUEST,
     * naming the cart it was issued for, and expiring in {@see $expiresIn} seconds.
     */
    #[Groups([self::GROUP_FRONT_READ])]
    public ?string $token = null;

    #[Groups([self::GROUP_FRONT_READ])]
    public ?int $expiresIn = null;

    #[Groups([self::GROUP_FRONT_READ])]
    public ?int $cartId = null;
}
