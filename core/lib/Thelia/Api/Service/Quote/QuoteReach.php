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

namespace Thelia\Api\Service\Quote;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Api\Security\AccountCaller;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\Service\CompanyPermissionChecker;
use Thelia\Domain\Quote\Service\QuoteAccess;
use Thelia\Model\Customer;
use Thelia\Model\Quote;
use Thelia\Model\QuoteQuery;

/**
 * The quote a storefront operation names, for the operations that write: the account it answers to,
 * and the quote under the rule of {@see QuoteOwnershipScope}, the one the reads are bounded by.
 *
 * A quote the account may not read answers 404, the answer a number that was never issued gets; one
 * it reads but whose role does not allow it to answer, 403.
 */
final readonly class QuoteReach
{
    public const string NOT_FOUND_MESSAGE = 'No such quote.';

    public function __construct(
        private AccountCaller $caller,
        private QuoteOwnershipScope $scope,
        private QuoteAccess $access,
        private CompanyPermissionChecker $permissionChecker,
    ) {
    }

    /**
     * The account of the call: a guest owns no quote and is refused.
     *
     * @throws AccessDeniedHttpException
     */
    public function customer(): Customer
    {
        return $this->caller->customer() ?? throw new AccessDeniedHttpException('An account is needed to ask for or answer a quote.');
    }

    /**
     * The account of the call when its role allows it to ask for quotes.
     *
     * @throws AccessDeniedHttpException
     */
    public function requestor(): Customer
    {
        $customer = $this->customer();

        if (!$this->access->canRequest($customer)) {
            throw new AccessDeniedHttpException('Your role does not allow you to ask for quotes.');
        }

        return $customer;
    }

    /**
     * @param array<string, mixed> $uriVariables
     *
     * @throws NotFoundHttpException when the quote does not exist or is out of reach
     */
    public function readable(Customer $customer, array $uriVariables): Quote
    {
        $quoteId = filter_var($uriVariables['id'] ?? null, \FILTER_VALIDATE_INT);

        if (false === $quoteId || $quoteId < 1) {
            throw new NotFoundHttpException(self::NOT_FOUND_MESSAGE);
        }

        $query = QuoteQuery::create()->filterById($quoteId);
        $this->scope->apply($query, $customer);

        return $query->findOne() ?? throw new NotFoundHttpException(self::NOT_FOUND_MESSAGE);
    }

    /**
     * @param array<string, mixed> $uriVariables
     *
     * @throws NotFoundHttpException
     * @throws AccessDeniedHttpException when the account reads the quote but may not answer it
     */
    public function answerable(Customer $customer, array $uriVariables): Quote
    {
        $quote = $this->readable($customer, $uriVariables);

        if (!$this->access->canAnswer($customer, $quote)) {
            throw new AccessDeniedHttpException('Your role does not allow you to answer this quote.');
        }

        return $quote;
    }

    /**
     * The quote the account may accept: one it may answer, and its role places orders, since the
     * acceptance leads to the order.
     *
     * @param array<string, mixed> $uriVariables
     *
     * @throws NotFoundHttpException
     * @throws AccessDeniedHttpException
     */
    public function acceptable(Customer $customer, array $uriVariables): Quote
    {
        $quote = $this->answerable($customer, $uriVariables);

        if (!$this->permissionChecker->can($customer, CompanyPermission::ORDER_PLACE)) {
            throw new AccessDeniedHttpException('Your role does not allow you to accept this quote.');
        }

        return $quote;
    }
}
