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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

readonly class CustomerCodeManager
{
    public function __construct(
        private MailerFactory $mailerFactory,
        #[Autowire(service: 'limiter.customer_code_request_per_email')]
        private RateLimiterFactoryInterface $perEmailCodeRequestLimiter,
        #[Autowire(service: 'limiter.customer_code_request_per_client')]
        private RateLimiterFactoryInterface $perClientCodeRequestLimiter,
        private RequestStack $requestStack,
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
        // No request in a CLI context: the per-client limit simply does not apply.
        // It is consumed first so that a caller who is already over it stops eating
        // the budget of the mailbox owner, who would then be denied a resend.
        $clientIp = $this->requestStack->getMainRequest()?->getClientIp();

        if (null !== $clientIp && !$this->perClientCodeRequestLimiter->create($clientIp)->consume()->isAccepted()) {
            return false;
        }

        if (!$this->perEmailCodeRequestLimiter->create(hash('sha256', strtolower((string) $customer->getEmail())))->consume()->isAccepted()) {
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
     * @throws \Exception
     */
    public function activateCustomerByCode(string $email, string $code): void
    {
        $customer = CustomerQuery::create()->findOneByEmail($email);
        if (!$customer) {
            throw new \Exception('Customer not found');
        }

        $customer->verifyActivationCode($code);

        $customer->setConfirmationToken(null);
        $customer->setConfirmationTokenExpiresAt(null);

        $customer->setEnable(1)->save();
    }
}
