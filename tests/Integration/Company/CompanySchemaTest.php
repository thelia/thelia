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

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Company;
use Thelia\Model\CompanyCustomer;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\CompanyQuery;
use Thelia\Model\CompanyRole;
use Thelia\Model\CompanyRoleQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\ResourceQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * What the tables of the company accounts hold and refuse, whoever writes them: the models, then
 * the rules of the database (unique account, cascade, set null, restrict) that no code is left to
 * keep.
 */
final class CompanySchemaTest extends IntegrationTestCase
{
    private FixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = $this->createFixtureFactory();
    }

    public function testACompanyKeepsWhatItIsGivenAndTheDefaultsOfItsColumns(): void
    {
        $company = new Company();
        $company->setName('Acme Industries');
        $company->save($this->connection());

        $reloaded = CompanyQuery::create()->findPk($company->getId());

        self::assertNotNull($reloaded);
        self::assertSame('Acme Industries', $reloaded->getName());
        self::assertSame(1, $reloaded->getActive(), 'A new company is active, and TINYINT reads as an int.');
        self::assertNull($reloaded->getSiret());
        self::assertNull($reloaded->getVatNumber());
        self::assertNull($reloaded->getParentId());
        self::assertNull($reloaded->getNotificationEmail());
        self::assertNull($reloaded->getInternalNote());
        self::assertNull($reloaded->getBillingAddressId());
        self::assertNotNull($reloaded->getCreatedAt());
    }

    public function testASiretAndAVatNumberAsLongAsTheOnesOfAnAddressFit(): void
    {
        $company = new Company();
        $company->setName('Acme');
        $company->setSiret(str_repeat('1', 20));
        $company->setVatNumber('FR'.str_repeat('2', 18));
        $company->setParentId(999999);
        $company->setNotificationEmail('purchasing@acme.test');
        $company->setInternalNote("Pays à 60 jours.\nContact: Mme Martin");
        $company->save($this->connection());

        $reloaded = CompanyQuery::create()->findPk($company->getId());

        self::assertSame(str_repeat('1', 20), $reloaded->getSiret());
        self::assertSame('FR'.str_repeat('2', 18), $reloaded->getVatNumber());
        self::assertSame(999999, $reloaded->getParentId(), 'The parent is a plain column: no company has to exist under that id.');
        self::assertSame('purchasing@acme.test', $reloaded->getNotificationEmail());
        self::assertSame("Pays à 60 jours.\nContact: Mme Martin", $reloaded->getInternalNote());
    }

    public function testARoleOfACompanyAndABuyerAreStoredWithTheirLimitsAsDecimalStrings(): void
    {
        $company = $this->company();
        $role = $this->role('purchaser', $company, ['order.place', 'order.view_own']);
        $buyer = $this->buyer($company, $role, orderLimit: '500.000000', periodLimit: '2000.5', period: 'month');

        $reloaded = CompanyCustomerQuery::create()->findPk($buyer->getId());

        self::assertSame($company->getId(), $reloaded->getCompanyId());
        self::assertSame($role->getId(), $reloaded->getCompanyRoleId());
        self::assertSame('500.000000', $reloaded->getOrderLimit(), 'DECIMAL reads as a string, with its six decimals.');
        self::assertSame('2000.500000', $reloaded->getPeriodLimit());
        self::assertSame('month', $reloaded->getPeriod());

        $withoutLimits = $this->buyer($company, $role);
        $reloaded = CompanyCustomerQuery::create()->findPk($withoutLimits->getId());

        self::assertNull($reloaded->getOrderLimit());
        self::assertNull($reloaded->getPeriodLimit());
        self::assertNull($reloaded->getPeriod());
    }

    public function testTheTitleOfARoleIsTranslated(): void
    {
        $role = $this->role('purchaser', null, ['order.place']);
        $role->setLocale('fr_FR')->setTitle('Acheteur principal');
        $role->save($this->connection());

        $reloaded = CompanyRoleQuery::create()->findPk($role->getId());

        self::assertSame('Purchaser', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame('Acheteur principal', $reloaded->setLocale('fr_FR')->getTitle());
    }

    public function testAnOrderKeepsTheCompanyAndTheAccountThatPlacedIt(): void
    {
        $company = $this->company();
        $owner = $this->customer();
        $placedBy = $this->customer();

        $order = $this->fixtures->order($owner);
        $order->setCompanyId($company->getId());
        $order->setPlacedByCustomerId($placedBy->getId());
        $order->save($this->connection());

        $reloaded = OrderQuery::create()->findPk($order->getId());

        self::assertSame($company->getId(), $reloaded->getCompanyId());
        self::assertSame($placedBy->getId(), $reloaded->getPlacedByCustomerId());
        self::assertSame($owner->getId(), $reloaded->getCustomerId());
    }

    public function testTheOwnerOfAnOrderKeepsItsNameAndTheBuyerAndTheCompanyHaveTheirOwn(): void
    {
        $company = $this->company();
        $owner = $this->customer();
        $placedBy = $this->customer();

        $order = $this->fixtures->order($owner);
        $order->setCompanyId($company->getId());
        $order->setPlacedByCustomerId($placedBy->getId());
        $order->save($this->connection());

        $reloaded = OrderQuery::create()->findPk($order->getId());

        self::assertSame($owner->getId(), $reloaded->getCustomer()->getId());
        self::assertSame($placedBy->getId(), $reloaded->getPlacedByCustomer()->getId());
        self::assertSame($company->getId(), $reloaded->getCompany()->getId());
        $freshBuyer = CustomerQuery::create()->findPk($placedBy->getId());
        $freshOwner = CustomerQuery::create()->findPk($owner->getId());

        self::assertSame([$order->getId()], array_map(static fn (Order $placed): int => $placed->getId(), iterator_to_array($freshBuyer->getPlacedOrders())));
        self::assertSame([$order->getId()], array_map(static fn (Order $owned): int => $owned->getId(), iterator_to_array($freshOwner->getOrders())));
    }

    public function testAnOrderOfACustomerAloneHasNoCompanyAndNoBuyer(): void
    {
        $reloaded = OrderQuery::create()->findPk($this->fixtures->order()->getId());

        self::assertNull($reloaded->getCompanyId());
        self::assertNull($reloaded->getPlacedByCustomerId());
    }

    public function testAnAddressCanBeSharedWithACompanyAndTheCreatorStaysItsOwner(): void
    {
        $company = $this->company();
        $creator = $this->customer();
        $address = $this->fixtures->address($creator, null, null, ['label' => 'Warehouse']);
        $address->setCompanyId($company->getId());
        $address->save($this->connection());

        $reloaded = AddressQuery::create()->findPk($address->getId());

        self::assertSame($company->getId(), $reloaded->getCompanyId());
        self::assertSame($creator->getId(), $reloaded->getCustomerId());
        self::assertSame($company->getId(), $reloaded->getSharingCompany()->getId());
        self::assertNull(AddressQuery::create()->findPk($this->fixtures->address($creator)->getId())->getCompanyId());
    }

    public function testAnAccountBelongsToOneCompanyAtMost(): void
    {
        $role = $this->role('purchaser', null, ['order.place']);
        $customer = $this->customer();
        $this->buyer($this->company(), $role, customer: $customer);

        $this->assertRefusedByTheDatabase(
            \sprintf(
                'INSERT INTO `company_customer` (`company_id`, `customer_id`, `company_role_id`) VALUES (%d, %d, %d)',
                $this->company('Second')->getId(),
                $customer->getId(),
                $role->getId(),
            ),
        );
    }

    public function testADeletedCustomerLeavesItsCompanyAndKeepsTheOrdersItPlaced(): void
    {
        $company = $this->company();
        $owner = $this->customer();
        $placedBy = $this->customer();
        $role = $this->role('purchaser', null, ['order.place']);
        $this->buyer($company, $role, customer: $placedBy);

        $order = $this->fixtures->order($owner);
        $order->setCompanyId($company->getId());
        $order->setPlacedByCustomerId($placedBy->getId());
        $order->save($this->connection());

        $this->connection()->exec('DELETE FROM `customer` WHERE `id` = '.$placedBy->getId());

        self::assertSame(0, CompanyCustomerQuery::create()->filterByCustomerId($placedBy->getId())->count(), 'The membership goes with the account.');

        $kept = OrderQuery::create()->findPk($order->getId());
        self::assertNull($kept->getPlacedByCustomerId());
        self::assertSame($company->getId(), $kept->getCompanyId(), 'The order keeps the company it was placed for.');
        self::assertNotNull(CompanyQuery::create()->findPk($company->getId()));
    }

    public function testAnInvitationDigestIsKeptOnceAndSurvivesTheDeletionOfItsAccount(): void
    {
        $company = $this->company();
        $customer = $this->customer();
        $digest = hash('sha256', 'a token');
        $insert = \sprintf(
            "INSERT INTO `company_invitation_use` (`fingerprint`, `company_id`, `customer_id`, `addressee`, `used_at`) VALUES ('%s', %d, %d, '%s', NOW())",
            $digest,
            $company->getId(),
            $customer->getId(),
            hash('sha256', (string) $customer->getEmail()),
        );
        $this->connection()->exec($insert);

        $this->assertRefusedByTheDatabase($insert);

        $this->connection()->exec('DELETE FROM `customer` WHERE `id` = '.$customer->getId());

        self::assertSame(
            [[$digest, (int) $company->getId(), null]],
            $this->connection()->query('SELECT `fingerprint`, `company_id`, `customer_id` FROM `company_invitation_use`')->fetchAll(\PDO::FETCH_NUM),
            'The digest outlives the account it took in: the invitation stays used.',
        );

        $this->connection()->exec('DELETE FROM `company` WHERE `id` = '.$company->getId());

        self::assertSame('0', (string) $this->connection()->query('SELECT COUNT(*) FROM `company_invitation_use`')->fetchColumn());
    }

    public function testACompanyThatHasAnOrderCannotBeDeleted(): void
    {
        $company = $this->company();
        $order = $this->fixtures->order();
        $order->setCompanyId($company->getId());
        $order->save($this->connection());

        $this->assertRefusedByTheDatabase('DELETE FROM `company` WHERE `id` = '.$company->getId());

        self::assertNotNull(CompanyQuery::create()->findPk($company->getId()));
    }

    public function testDeletingACompanyWithNoOrderTakesItsBuyersAndItsRolesAndLeavesItsAddresses(): void
    {
        $company = $this->company();
        $customer = $this->customer();
        $ownRole = $this->role('site-manager', $company, ['order.place']);
        $this->buyer($company, $ownRole, customer: $customer);
        $address = $this->fixtures->address($customer);
        $address->setCompanyId($company->getId());
        $address->save($this->connection());
        $company->setBillingAddressId($address->getId());
        $company->save($this->connection());

        $this->connection()->exec('DELETE FROM `company` WHERE `id` = '.$company->getId());

        self::assertSame(0, CompanyCustomerQuery::create()->filterByCompanyId($company->getId())->count());
        self::assertSame(0, CompanyRoleQuery::create()->filterByCompanyId($company->getId())->count());

        $remaining = AddressQuery::create()->findPk($address->getId());
        self::assertInstanceOf(Address::class, $remaining, 'The address belongs to its creator, not to the company.');
        self::assertNull($remaining->getCompanyId());
        self::assertNotNull(CustomerQuery::create()->findPk($customer->getId()));
    }

    public function testARoleStillGivenToABuyerCannotBeDeleted(): void
    {
        $role = $this->role('purchaser', null, ['order.place']);
        $this->buyer($this->company(), $role);

        $this->assertRefusedByTheDatabase('DELETE FROM `company_role` WHERE `id` = '.$role->getId());
    }

    public function testADeletedBillingAddressLeavesTheCompanyWithNoBillingAddress(): void
    {
        $company = $this->company();
        $address = $this->fixtures->address($this->customer());
        $company->setBillingAddressId($address->getId());
        $company->save($this->connection());

        $this->connection()->exec('DELETE FROM `address` WHERE `id` = '.$address->getId());

        self::assertNull(CompanyQuery::create()->findPk($company->getId())->getBillingAddressId());
        self::assertNotNull(CompanyQuery::create()->findPk($company->getId()));
    }

    public function testTheThreeRolesEveryCompanyMayGiveAreShippedWithTheirPermissions(): void
    {
        $expected = [
            'company_admin' => ['order.place', 'order.view_own', 'order.view_company', 'address.manage_shared', 'buyer.manage', 'company.edit'],
            'buyer' => ['order.place', 'order.view_own'],
            'approver' => ['order.view_own', 'order.view_company', 'order.approve'],
        ];

        $shipped = [];
        foreach (CompanyRoleQuery::create()->filterByCompanyId(null)->orderById() as $role) {
            $shipped[$role->getCode()] = json_decode($role->getPermissions(), true, 512, \JSON_THROW_ON_ERROR);
        }

        self::assertSame($expected, $shipped);
    }

    public function testTheShippedRolesAreTitledInTheLanguagesOfTheInstall(): void
    {
        $titles = [];
        foreach (CompanyRoleQuery::create()->filterByCompanyId(null) as $role) {
            $titles[$role->getCode()] = [
                'en_US' => $role->setLocale('en_US')->getTitle(),
                'fr_FR' => $role->setLocale('fr_FR')->getTitle(),
            ];
        }

        self::assertSame(
            [
                'company_admin' => ['en_US' => 'Company administrator', 'fr_FR' => 'Administrateur de la société'],
                'buyer' => ['en_US' => 'Buyer', 'fr_FR' => 'Acheteur'],
                'approver' => ['en_US' => 'Approver', 'fr_FR' => 'Valideur'],
            ],
            $titles,
        );
    }

    public function testTheCompanyResourceIsSeededUnderItsFixedIdAndNamedInEveryLanguage(): void
    {
        self::assertSame('admin.company', AdminResources::COMPANY);

        $resource = ResourceQuery::create()->findOneByCode(AdminResources::COMPANY);

        self::assertNotNull($resource);
        self::assertSame(72, $resource->getId());
        self::assertSame('Companies', $resource->setLocale('en_US')->getTitle());
        self::assertSame('Sociétés', $resource->setLocale('fr_FR')->getTitle());
    }

    private function assertRefusedByTheDatabase(string $statement): void
    {
        try {
            $this->connection()->exec($statement);
        } catch (\PDOException $refusal) {
            self::assertSame('23000', (string) $refusal->getCode(), $refusal->getMessage());

            return;
        }

        self::fail('The database accepted: '.$statement);
    }

    private function connection(): ConnectionInterface
    {
        return $this->getPropelConnection();
    }

    private function customer(): Customer
    {
        return $this->fixtures->customer($this->fixtures->customerTitle());
    }

    private function company(string $name = 'Acme'): Company
    {
        $company = new Company();
        $company->setName($name);
        $company->save($this->connection());

        return $company;
    }

    /**
     * @param list<string> $permissions
     */
    private function role(string $code, ?Company $company, array $permissions): CompanyRole
    {
        $role = new CompanyRole();
        $role->setCode($code);
        $role->setCompanyId($company?->getId());
        $role->setPermissions(json_encode($permissions, \JSON_THROW_ON_ERROR));
        $role->setLocale('en_US')->setTitle(ucfirst($code));
        $role->save($this->connection());

        return $role;
    }

    private function buyer(
        Company $company,
        CompanyRole $role,
        ?string $orderLimit = null,
        ?string $periodLimit = null,
        ?string $period = null,
        ?Customer $customer = null,
    ): CompanyCustomer {
        $buyer = new CompanyCustomer();
        $buyer->setCompanyId($company->getId());
        $buyer->setCustomerId(($customer ?? $this->customer())->getId());
        $buyer->setCompanyRoleId($role->getId());
        $buyer->setOrderLimit($orderLimit);
        $buyer->setPeriodLimit($periodLimit);
        $buyer->setPeriod($period);
        $buyer->save($this->connection());

        return $buyer;
    }
}
