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

namespace Thelia\Tests\Integration\Company;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Addressing\AddressFacade;
use Thelia\Domain\Addressing\Service\AddressAccess;
use Thelia\Domain\Company\CompanyFacade;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Domain\Company\Service\CompanyPermissionChecker;
use Thelia\Model\Cart;
use Thelia\Model\Company;
use Thelia\Model\Customer;

/**
 * The companies and the address books the address tests start from. The services are the ones of
 * the container, so what the checkout reads is what the test changed.
 */
abstract class AddressTestCase extends CompanyDomainTestCase
{
    protected AddressAccess $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = $this->getService(CompanyMembershipReader::class);
        $this->facade = $this->getService(CompanyFacade::class);
        $this->checker = $this->getService(CompanyPermissionChecker::class);
        $this->access = $this->getService(AddressAccess::class);
    }

    /**
     * Two companies with an administrator and a buyer each.
     *
     * @return array{first: Company, second: Company, administratorOfFirst: Customer, creator: Customer, colleague: Customer, administratorOfSecond: Customer, buyerOfSecond: Customer}
     */
    protected function twoCompanies(): array
    {
        $first = $this->company('Acme');
        $second = $this->company('Globex');

        return [
            'first' => $first,
            'second' => $second,
            'administratorOfFirst' => $this->buyerOf($first, CompanyRoleCode::ADMINISTRATOR),
            'creator' => $this->buyerOf($first),
            'colleague' => $this->buyerOf($first),
            'administratorOfSecond' => $this->buyerOf($second, CompanyRoleCode::ADMINISTRATOR),
            'buyerOfSecond' => $this->buyerOf($second),
        ];
    }

    /**
     * The facade of the addresses as the given customer, signed in, uses it.
     */
    protected function addressFacadeOf(Customer $customer): AddressFacade
    {
        $session = $this->createStub(Session::class);
        $session->method('getCustomerUser')->willReturn($customer);

        $dispatcher = $this->getService(EventDispatcherInterface::class);

        return new AddressFacade($dispatcher, $session, $this->access);
    }

    protected function cartOf(Customer $customer): Cart
    {
        return $this->fixtures->cart($customer);
    }
}
