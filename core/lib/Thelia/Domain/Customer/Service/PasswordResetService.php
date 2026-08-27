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

namespace Thelia\Domain\Customer\Service;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Domain\Customer\Exception\InvalidPasswordResetTokenException;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

/**
 * Mails a customer a way back into an account whose password is lost, and takes the
 * new password once that way back is presented again.
 *
 * The link carries a token that is signed rather than stored: it names the account and
 * the moment it stops being accepted, and it is signed together with the password hash
 * it was issued against. Nothing is written when the link is sent, the token stops being
 * accepted the moment the password behind it changes — which makes the successful reset
 * the last use of the link — and a link issued for one account cannot be replayed on
 * another, since the signature no longer matches.
 */
readonly class PasswordResetService
{
    /**
     * A link is meant to be followed right away, so it is short-lived: long enough
     * for a mail to arrive and be read, short enough that a mailbox opened later by
     * someone else is not an open door to the account.
     */
    public const DEFAULT_LINK_LIFETIME_IN_SECONDS = 3600;

    /**
     * Overrides the lifetime above, in seconds, when the shop has an opinion.
     */
    public const LINK_LIFETIME_CONFIG_KEY = 'password_reset_link_lifetime';

    private const SIGNATURE_ALGORITHM = 'sha256';

    public function __construct(
        private MailerFactory $mailerFactory,
        private CustomerEmailRequestLimiter $emailRequestLimiter,
        #[Autowire(param: 'kernel.secret')]
        private string $applicationSecret,
    ) {
    }

    /**
     * Mail the owner of an address a link to choose a new password, if that address
     * has an account and the shop has not already been asked too often.
     *
     * The address comes from whoever asked, so nothing here may tell the caller whether
     * it names an account: this returns the same way in every case, and callers must
     * answer the visitor the same way too.
     *
     * @throws PropelException
     */
    public function requestResetLink(string $email): void
    {
        // Taken before the address is looked up, so a caller spends the same budget
        // whether or not the address has an account.
        if (!$this->emailRequestLimiter->allows($email)) {
            return;
        }

        $customer = CustomerQuery::create()->filterByEmail($email)->findOne();

        if (!$customer instanceof Customer) {
            return;
        }

        $this->mailerFactory->sendEmailToCustomer(
            'lost_password',
            $customer,
            [
                'token' => $this->createToken($customer),
                'tokenLifetimeInMinutes' => intdiv($this->getLinkLifetimeInSeconds(), 60),
            ],
        );
    }

    /**
     * Give the account named by a token the password its owner just chose.
     *
     * @throws InvalidPasswordResetTokenException when the token is not one this shop
     *                                            issued, no longer matches the account, or has expired
     * @throws PropelException
     */
    public function resetPassword(string $token, string $plainPassword): Customer
    {
        $customer = $this->findCustomerForToken($token);

        if (!$customer instanceof Customer) {
            throw new InvalidPasswordResetTokenException('This password reset link is no longer valid.');
        }

        $customer->setPassword($plainPassword)->save();

        return $customer;
    }

    /**
     * Build the token that names this account in a reset link.
     */
    public function createToken(Customer $customer, ?int $lifetimeInSeconds = null): string
    {
        $customerId = (int) $customer->getId();
        $expiresAt = time() + ($lifetimeInSeconds ?? $this->getLinkLifetimeInSeconds());

        return \sprintf(
            '%d.%d.%s',
            $customerId,
            $expiresAt,
            $this->sign($customerId, $expiresAt, (string) $customer->getEmail(), $customer->getPassword()),
        );
    }

    /**
     * The account a token names, or null when the token is not one this shop issued,
     * no longer matches the account, or has expired.
     */
    public function findCustomerForToken(string $token): ?Customer
    {
        $parts = explode('.', $token);

        if (3 !== \count($parts)) {
            return null;
        }

        [$rawCustomerId, $rawExpiresAt, $signature] = $parts;

        if (!ctype_digit($rawCustomerId) || !ctype_digit($rawExpiresAt)) {
            return null;
        }

        $customerId = (int) $rawCustomerId;
        $expiresAt = (int) $rawExpiresAt;
        $customer = CustomerQuery::create()->findPk($customerId);

        // Signed even when the account is gone, and always compared, so an id that names
        // no account and a signature that does not match take the same path and the same time.
        $expectedSignature = $this->sign(
            $customerId,
            $expiresAt,
            (string) $customer?->getEmail(),
            (string) $customer?->getPassword(),
        );

        if (!hash_equals($expectedSignature, $signature) || !$customer instanceof Customer) {
            return null;
        }

        if ($expiresAt <= time()) {
            return null;
        }

        return $customer;
    }

    public function getLinkLifetimeInSeconds(): int
    {
        $configured = (int) ConfigQuery::read(
            self::LINK_LIFETIME_CONFIG_KEY,
            (string) self::DEFAULT_LINK_LIFETIME_IN_SECONDS,
        );

        // A lifetime of zero or less would hand out links nothing can accept, which reads
        // as a broken shop rather than as a strict one.
        return $configured > 0 ? $configured : self::DEFAULT_LINK_LIFETIME_IN_SECONDS;
    }

    /**
     * The password hash is part of what is signed, and it is replaced on every save, so
     * a token stops being accepted as soon as the password behind it changes — including
     * the change the token was issued for. The address is signed too, so a link cannot
     * outlive the mailbox it was sent to.
     */
    private function sign(int $customerId, int $expiresAt, string $email, string $passwordHash): string
    {
        $key = hash_hmac(
            self::SIGNATURE_ALGORITHM,
            'thelia.customer.password_reset',
            $this->applicationSecret,
            true,
        );

        return hash_hmac(
            self::SIGNATURE_ALGORITHM,
            implode("\0", [$customerId, $expiresAt, $email, $passwordHash]),
            $key,
        );
    }
}
