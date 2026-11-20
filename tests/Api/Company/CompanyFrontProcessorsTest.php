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

namespace Thelia\Tests\Api\Company;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Thelia\Api\Resource\Company as CompanyResource;
use Thelia\Api\Resource\CompanyCustomer as CompanyCustomerResource;
use Thelia\Api\Security\AccountCaller;
use Thelia\Api\State\Processor\CompanyCustomerFrontDetachProcessor;
use Thelia\Api\State\Processor\CompanyCustomerFrontUpdateProcessor;
use Thelia\Api\State\Processor\CompanyFrontUpdateProcessor;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Tests\Integration\Company\AddressTestCase;

/**
 * The processors of the account operations check the rights of the caller again, whatever got them
 * called: the security rule and the query scope come first, and are not what these rely on.
 */
final class CompanyFrontProcessorsTest extends AddressTestCase
{
    use LogsInAsCustomer;

    public function testABuyerWithoutTheRightToManageTheBuyersDetachesNobody(): void
    {
        $companies = $this->twoCompanies();
        $this->loginAsCustomerInSession($companies['colleague']);
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($companies['creator']->getId());

        $this->expectException(NotFoundHttpException::class);

        try {
            $this->getService(CompanyCustomerFrontDetachProcessor::class)->process(null, new Delete(), ['id' => $link->getId()]);
        } finally {
            self::assertNotNull(CompanyCustomerQuery::create()->findPk($link->getId()));
        }
    }

    public function testAnAdministratorDetachesNobodyOfAnotherCompany(): void
    {
        $companies = $this->twoCompanies();
        $this->loginAsCustomerInSession($companies['administratorOfFirst']);
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($companies['buyerOfSecond']->getId());

        $this->expectException(NotFoundHttpException::class);

        try {
            $this->getService(CompanyCustomerFrontDetachProcessor::class)->process(null, new Delete(), ['id' => $link->getId()]);
        } finally {
            self::assertNotNull(CompanyCustomerQuery::create()->findPk($link->getId()));
        }
    }

    public function testABuyerWithoutTheRightToManageTheBuyersChangesNobody(): void
    {
        $companies = $this->twoCompanies();
        $this->loginAsCustomerInSession($companies['colleague']);
        $link = CompanyCustomerQuery::create()->findOneByCustomerId($companies['creator']->getId());

        $this->expectException(NotFoundHttpException::class);

        $this->getService(CompanyCustomerFrontUpdateProcessor::class)->process((new CompanyCustomerResource())->setRoleCode('approver'), new Patch(), ['id' => $link->getId()]);
    }

    public function testABuyerWithoutTheRightToEditTheCompanyEditsNothing(): void
    {
        $companies = $this->twoCompanies();
        $this->loginAsCustomerInSession($companies['colleague']);

        $this->expectException(AccessDeniedHttpException::class);

        try {
            $this->getService(CompanyFrontUpdateProcessor::class)->process((new CompanyResource())->setName('Mine'), new Patch(), []);
        } finally {
            self::assertSame('Acme', \Thelia\Model\CompanyQuery::create()->findPk($companies['first']->getId())?->getName());
        }
    }

    public function testAnAccountOfNoCompanyEditsNothing(): void
    {
        $this->loginAsCustomerInSession($this->customer());

        $this->expectException(AccessDeniedHttpException::class);

        $this->getService(CompanyFrontUpdateProcessor::class)->process((new CompanyResource())->setName('Mine'), new Patch(), []);
    }

    public function testAGuestOfTheTokenIsNobodyForTheAccountEndpoints(): void
    {
        $guest = $this->guest();
        $tokenStorage = $this->getService(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken($guest, 'front', $guest->getRoles()));

        try {
            self::assertNull($this->getService(AccountCaller::class)->customer());
        } finally {
            $tokenStorage->setToken(null);
        }
    }

    public function testACustomerOfTheTokenIsTheCaller(): void
    {
        $customer = $this->customer();
        $tokenStorage = $this->getService(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken($customer, 'front', $customer->getRoles()));

        try {
            self::assertSame($customer->getId(), $this->getService(AccountCaller::class)->customer()?->getId());
        } finally {
            $tokenStorage->setToken(null);
        }
    }
}
