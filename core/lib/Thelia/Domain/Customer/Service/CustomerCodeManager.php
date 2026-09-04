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
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

readonly class CustomerCodeManager
{
    public function __construct(
        private MailerFactory $mailerFactory,
        private CustomerEmailRequestLimiter $emailRequestLimiter,
    ) {
    }

    /**
     * Send a fresh activation code, unless codes have already been asked for too often.
     *
     * This is the entry point for "send me the code again": it is reachable by anyone
     * who knows an address, so the number of emails it can trigger has to be capped.
     * Callers must answer the visitor the same way whether this returned true or false,
     * otherwise the answer tells the caller whether the address has an account.
     *
     * @throws PropelException
     */
    public function requestCode(
        Customer $customer,
        int $expiryTimeInHours = 24,
    ): bool {
        if (!$this->emailRequestLimiter->allows((string) $customer->getEmail())) {
            return false;
        }

        $this->createCodeAndSendIt($customer, $expiryTimeInHours);

        return true;
    }

    /**
     * @throws PropelException
     */
    public function createCodeAndSendIt(
        Customer $customer,
        int $expiryTimeInHours = 24,
    ): void {
        $code = $customer->setConfirmationTokenWithExpiry($expiryTimeInHours);
        $customer->save();

        $this->mailerFactory->sendEmailToCustomer(
            'customer_send_code',
            $customer,
            [
                'code' => $code,
                'expiryTime' => $expiryTimeInHours,
                'customer' => $customer,
            ]
        );
    }

    /**
     * Open the account the code was mailed for.
     *
     * This is also where a converted guest stops being one: the conversion gives the row
     * a password, and answering the code is the proof that the password was chosen by
     * whoever reads the mailbox. Until then the row is still a guest, and a guest signs
     * into nothing.
     *
     * @throws \Exception
     */
    public function activateCustomerByCode(string $email, string $code): void
    {
        $customer = $this->pendingCustomerForCode($email, $code);

        if (!$customer instanceof Customer) {
            throw new \Exception('Customer not found');
        }

        $customer->verifyActivationCode($code);

        $customer->setConfirmationToken(null);
        $customer->setConfirmationTokenExpiresAt(null);

        $customer
            ->setIsGuest(0)
            ->setEnable(1)
            ->save();
    }

    /**
     * The row this code belongs to, among those sharing the address.
     *
     * An address can carry both a guest row and a real account: a guest row no longer
     * blocks a registration, so the shop may hold one of each. The code itself is what
     * tells them apart — it was mailed for exactly one of them — so the row whose pending
     * token the code verifies against is the row being activated. Falling back to the
     * plain lookup keeps the "no such account" answer of every other case unchanged.
     */
    private function pendingCustomerForCode(string $email, string $code): ?Customer
    {
        $fallback = null;

        foreach (CustomerQuery::create()->filterByEmail($email)->find() as $candidate) {
            $fallback ??= $candidate;

            try {
                $candidate->verifyActivationCode($code);
            } catch (\Exception) {
                continue;
            }

            return $candidate;
        }

        return $fallback;
    }
}
