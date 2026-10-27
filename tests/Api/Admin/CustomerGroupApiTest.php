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

namespace Thelia\Tests\Api\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerGroupChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\CustomerGroupCategoryQuery;
use Thelia\Model\CustomerGroupPaymentModuleQuery;
use Thelia\Model\CustomerGroupProductPriceQuery;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Module;
use Thelia\Module\BaseModule;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The customer groups are managed through the admin API: their wording, discount,
 * tax display, position, the lists of payment modules and categories they are
 * restricted to (their own, an empty one meaning everything), and the prices they pay.
 */
final class CustomerGroupApiTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private const ALL_ACCESSES = [
        AccessManager::VIEW,
        AccessManager::CREATE,
        AccessManager::UPDATE,
        AccessManager::DELETE,
    ];

    public function testAGroupIsCreatedReadBackAndUpdated(): void
    {
        $token = $this->authenticateAsAdmin();

        $created = $this->decode($this->jsonRequest('POST', '/api/admin/customer_groups', [
            'code' => 'professionals',
            'position' => 3,
            'discount' => 12.5,
            'displayTaxedPrice' => false,
            'i18ns' => [
                'en_US' => ['title' => 'Professionals', 'description' => 'Resellers'],
                'fr_FR' => ['title' => 'Professionnels'],
            ],
        ], $token), 201);

        self::assertSame('professionals', $created['code']);
        self::assertSame('/api/admin/customer_groups/'.$created['id'], $created['@id']);

        $read = $this->decode($this->jsonRequest('GET', '/api/admin/customer_groups/'.$created['id'], token: $token));
        self::assertSame(3, $read['position']);
        self::assertEquals(12.5, $read['discount']);
        self::assertFalse($read['displayTaxedPrice']);
        self::assertFalse($read['isDefault']);
        self::assertSame('Professionnels', $read['i18ns']['fr_FR']['title']);
        self::assertSame('Resellers', $read['i18ns']['en_US']['description']);
        self::assertSame([], $read['paymentModules']);
        self::assertSame([], $read['categories']);

        $patched = $this->decode($this->jsonRequest(
            'PATCH',
            '/api/admin/customer_groups/'.$created['id'],
            ['position' => 9, 'discount' => 20.0, 'i18ns' => ['en_US' => ['title' => 'Pros']]],
            $token,
            'merge-patch+json',
        ));
        self::assertSame(9, $patched['position']);

        $stored = CustomerGroupQuery::create()->findPk($created['id']);
        self::assertSame(9, $stored->getPosition());
        self::assertEquals(20.0, (float) $stored->getDiscount());
        self::assertSame('professionals', $stored->getCode());
        self::assertSame(0, (int) $stored->getDisplayTaxedPrice(), 'a patch leaves the other fields alone');
    }

    public function testTheCollectionIsOrderedByPositionAndFilteredByCodeAndDefault(): void
    {
        $factory = $this->createFixtureFactory();
        $factory->customerGroup('zz-late');
        $factory->customerGroup('aa-early');
        $token = $this->authenticateAsAdmin();

        $exact = $this->decode($this->jsonRequest('GET', '/api/admin/customer_groups?code=aa-early', token: $token));
        self::assertSame(['aa-early'], array_column($exact['hydra:member'], 'code'));

        $defaults = $this->decode($this->jsonRequest('GET', '/api/admin/customer_groups?isDefault=true', token: $token));
        self::assertCount(1, $defaults['hydra:member']);
        self::assertTrue($defaults['hydra:member'][0]['isDefault']);

        $ordered = $this->decode($this->jsonRequest('GET', '/api/admin/customer_groups?order[position]=desc', token: $token));
        $positions = array_column($ordered['hydra:member'], 'position');
        $sorted = $positions;
        rsort($sorted);
        self::assertSame($sorted, $positions);
        self::assertGreaterThan(1, \count($positions));
    }

    public function testADuplicateCodeIsRefusedAsAViolation(): void
    {
        $this->createFixtureFactory()->customerGroup('taken');
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/customer_groups', ['code' => 'taken'], $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(1, CustomerGroupQuery::create()->filterByCode('taken')->count());
    }

    public function testADiscountOutsideZeroToOneHundredIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/customer_groups', ['code' => 'too-generous', 'discount' => 140], $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testThePaymentModulesAndCategoriesAreWrittenByIriAndReadBack(): void
    {
        $factory = $this->createFixtureFactory();
        $moduleA = $this->paymentModule('A1PayA');
        $moduleB = $this->paymentModule('A1PayB');
        $categoryA = $factory->category();
        $categoryB = $factory->category();
        $token = $this->authenticateAsAdmin();

        $created = $this->decode($this->jsonRequest('POST', '/api/admin/customer_groups', [
            'code' => 'restricted',
            'paymentModules' => ['/api/admin/modules/'.$moduleA->getId(), '/api/admin/modules/'.$moduleB->getId()],
            'categories' => ['/api/admin/categories/'.$categoryA->getId()],
        ], $token), 201);

        self::assertEqualsCanonicalizing(
            ['/api/admin/modules/'.$moduleA->getId(), '/api/admin/modules/'.$moduleB->getId()],
            $created['paymentModules'],
        );
        self::assertSame(['/api/admin/categories/'.$categoryA->getId()], $created['categories']);
        self::assertSame(2, CustomerGroupPaymentModuleQuery::create()->filterByCustomerGroupId($created['id'])->count());

        // A write that leaves the lists out keeps them.
        $this->decode($this->jsonRequest('PATCH', '/api/admin/customer_groups/'.$created['id'], ['position' => 4], $token, 'merge-patch+json'));
        self::assertSame(2, CustomerGroupPaymentModuleQuery::create()->filterByCustomerGroupId($created['id'])->count());
        self::assertSame(1, CustomerGroupCategoryQuery::create()->filterByCustomerGroupId($created['id'])->count());

        // A list replaces the previous one.
        $put = $this->decode($this->jsonRequest('PUT', '/api/admin/customer_groups/'.$created['id'], [
            'code' => 'restricted',
            'paymentModules' => ['/api/admin/modules/'.$moduleB->getId()],
            'categories' => ['/api/admin/categories/'.$categoryB->getId()],
        ], $token));
        self::assertSame(['/api/admin/modules/'.$moduleB->getId()], $put['paymentModules']);
        self::assertSame(
            [(int) $moduleB->getId()],
            array_map('intval', CustomerGroupPaymentModuleQuery::create()->filterByCustomerGroupId($created['id'])->select('ModuleId')->find()->toArray()),
        );
        self::assertSame(
            [(int) $categoryB->getId()],
            array_map('intval', CustomerGroupCategoryQuery::create()->filterByCustomerGroupId($created['id'])->select('CategoryId')->find()->toArray()),
        );

        // An empty list clears the links: the group sees the whole catalog and every module again.
        $cleared = $this->decode($this->jsonRequest('PATCH', '/api/admin/customer_groups/'.$created['id'], ['paymentModules' => [], 'categories' => []], $token, 'merge-patch+json'));
        self::assertSame([], $cleared['paymentModules']);
        self::assertSame(0, CustomerGroupPaymentModuleQuery::create()->filterByCustomerGroupId($created['id'])->count());
        self::assertSame(0, CustomerGroupCategoryQuery::create()->filterByCustomerGroupId($created['id'])->count());
    }

    /**
     * @param array<string, mixed> $links
     */
    #[DataProvider('invalidLinks')]
    public function testALinkThatIsNotAnExistingIriIsRefusedAndNothingIsWritten(array $links): void
    {
        $token = $this->authenticateAsAdmin();
        $notPayment = (new Module())
            ->setCode('A1NotPayment')
            ->setFullNamespace(self::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::CLASSIC_MODULE_TYPE)
            ->setCategory('classic')
            ->setActivate(BaseModule::IS_ACTIVATED);
        $notPayment->save($this->getPropelConnection());

        if (isset($links['paymentModules']) && ['not-payment'] === $links['paymentModules']) {
            $links['paymentModules'] = ['/api/admin/modules/'.$notPayment->getId()];
        }

        $response = $this->jsonRequest('POST', '/api/admin/customer_groups', ['code' => 'bad-links'] + $links, $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, CustomerGroupQuery::create()->filterByCode('bad-links')->count());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidLinks(): iterable
    {
        yield 'a module that does not exist' => [['paymentModules' => ['/api/admin/modules/999999']]];
        yield 'a module that is not a payment module' => [['paymentModules' => ['not-payment']]];
        yield 'a category that does not exist' => [['categories' => ['/api/admin/categories/999999']]];
        yield 'an IRI of another resource' => [['categories' => ['/api/admin/products/1']]];
        yield 'a bare id' => [['categories' => [1]]];
    }

    public function testTheDefaultGroupCannotBeDeleted(): void
    {
        $token = $this->authenticateAsAdmin();
        $default = CustomerGroupQuery::findDefault();

        $response = $this->jsonRequest('DELETE', '/api/admin/customer_groups/'.$default->getId(), token: $token);

        self::assertSame(409, $response->getStatusCode(), (string) $response->getContent());
        self::assertNotNull(CustomerGroupQuery::create()->findPk($default->getId()));
    }

    /**
     * The lists of a group are its own: deleting it, or emptying them, opens nothing to
     * another group, so nothing is refused.
     */
    public function testAGroupThatListsCategoriesAndModulesIsDeleted(): void
    {
        $factory = $this->createFixtureFactory();
        $module = $this->paymentModule('N2PayOnly');
        $category = $factory->category();
        $group = $factory->customerGroup('only-door', paymentModuleIds: [(int) $module->getId()], categoryIds: [(int) $category->getId()]);
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('DELETE', '/api/admin/customer_groups/'.$group->getId(), token: $token);

        self::assertSame(204, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(CustomerGroupQuery::create()->findPk($group->getId()));
        self::assertSame(0, CustomerGroupCategoryQuery::create()->filterByCustomerGroupId($group->getId())->count());
        self::assertSame(0, CustomerGroupPaymentModuleQuery::create()->filterByCustomerGroupId($group->getId())->count());
    }

    public function testTheListsOfTheOnlyGroupThatListsACategoryOrAModuleCanBeEmptiedOnEveryWrite(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $module = $this->paymentModule('N2WritePay');
        $token = $this->authenticateAsAdmin();

        foreach ([
            ['PATCH', ['categories' => [], 'paymentModules' => [], 'position' => 77], 'merge-patch+json'],
            ['PUT', ['code' => 'only-door-write-put', 'categories' => [], 'paymentModules' => []], 'jsonld'],
        ] as [$method, $body, $format]) {
            $group = $factory->customerGroup('PUT' === $method ? 'only-door-write-put' : 'only-door-write-patch', paymentModuleIds: [(int) $module->getId()], categoryIds: [(int) $category->getId()]);

            $response = $this->jsonRequest($method, '/api/admin/customer_groups/'.$group->getId(), $body, $token, $format);

            self::assertSame(200, $response->getStatusCode(), $method.' '.$response->getContent());
            self::assertSame(0, CustomerGroupCategoryQuery::create()->filterByCustomerGroupId($group->getId())->count(), $method);
            self::assertSame(0, CustomerGroupPaymentModuleQuery::create()->filterByCustomerGroupId($group->getId())->count(), $method);
        }
    }

    public function testADiscountIsAcceptedFromZeroToOneHundredAndRefusedOutsideOnEveryWrite(): void
    {
        $token = $this->authenticateAsAdmin();

        foreach ([0, 100, 99.99] as $accepted) {
            $response = $this->jsonRequest('POST', '/api/admin/customer_groups', ['code' => 'rate-'.str_replace('.', '-', (string) $accepted), 'discount' => $accepted], $token);
            self::assertSame(201, $response->getStatusCode(), $accepted.' '.$response->getContent());
        }

        $group = $this->createFixtureFactory()->customerGroup('rate-patched', 10.0);

        foreach ([-0.01, 100.01, -5, 150] as $refused) {
            self::assertSame(422, $this->jsonRequest('POST', '/api/admin/customer_groups', ['code' => 'rate-refused', 'discount' => $refused], $token)->getStatusCode(), 'POST '.$refused);
            self::assertSame(422, $this->jsonRequest('PATCH', '/api/admin/customer_groups/'.$group->getId(), ['discount' => $refused], $token, 'merge-patch+json')->getStatusCode(), 'PATCH '.$refused);
            self::assertSame(422, $this->jsonRequest('PUT', '/api/admin/customer_groups/'.$group->getId(), ['code' => 'rate-patched', 'discount' => $refused], $token)->getStatusCode(), 'PUT '.$refused);
        }

        self::assertSame(10.0, (float) CustomerGroupQuery::create()->findPk($group->getId())->getDiscount());
        self::assertSame(0, CustomerGroupQuery::create()->filterByCode('rate-refused')->count());
    }

    public function testMovingACustomerThroughTheAdminApiIsAnnouncedOnceAndOnlyWhenItMoves(): void
    {
        $factory = $this->createFixtureFactory();
        $pro = $factory->customerGroup('moved-by-api');
        $customer = $factory->customer($factory->customerTitle());
        $previousGroupId = (int) $customer->getCustomerGroupId();
        $token = $this->authenticateAsAdmin();
        $heard = [];
        $this->getService(EventDispatcherInterface::class)->addListener(
            TheliaEvents::CUSTOMER_GROUP_CHANGED,
            static function (CustomerGroupChangedEvent $event) use (&$heard): void {
                $heard[] = [$event->getCustomer()->getId(), $event->getPreviousCustomerGroupId(), $event->getCustomerGroupId()];
            },
        );

        // A write that does not touch the group announces nothing.
        $this->decode($this->jsonRequest('PATCH', '/api/admin/customers/'.$customer->getId(), ['firstname' => 'Renamed'], $token, 'merge-patch+json'));
        self::assertSame([], $heard);

        $moved = $this->decode($this->jsonRequest('PATCH', '/api/admin/customers/'.$customer->getId(), ['customerGroup' => '/api/admin/customer_groups/'.$pro->getId()], $token, 'merge-patch+json'));

        self::assertSame('/api/admin/customer_groups/'.$pro->getId(), \is_array($moved['customerGroup']) ? $moved['customerGroup']['@id'] : $moved['customerGroup']);
        self::assertSame([[$customer->getId(), $previousGroupId, $pro->getId()]], $heard);

        // Written again to the same group: nothing moved, nothing announced.
        $this->decode($this->jsonRequest('PATCH', '/api/admin/customers/'.$customer->getId(), ['customerGroup' => '/api/admin/customer_groups/'.$pro->getId()], $token, 'merge-patch+json'));
        self::assertCount(1, $heard);
    }

    public function testTheOnlyDefaultGroupCannotStopBeingTheDefault(): void
    {
        $token = $this->authenticateAsAdmin();
        $default = CustomerGroupQuery::findDefault();

        foreach (['PATCH' => 'merge-patch+json', 'PUT' => 'jsonld'] as $method => $format) {
            $response = $this->jsonRequest(
                $method,
                '/api/admin/customer_groups/'.$default->getId(),
                ['code' => $default->getCode(), 'isDefault' => false],
                $token,
                $format,
            );

            self::assertSame(409, $response->getStatusCode(), $method.' '.$response->getContent());
        }

        self::assertSame($default->getId(), CustomerGroupQuery::findDefault()->getId());
    }

    public function testFlaggingAnotherGroupReleasesTheDefaultOne(): void
    {
        $token = $this->authenticateAsAdmin();
        $previous = CustomerGroupQuery::findDefault();
        $other = $this->createFixtureFactory()->customerGroup('next-default');

        $this->decode($this->jsonRequest('PATCH', '/api/admin/customer_groups/'.$other->getId(), ['isDefault' => true], $token, 'merge-patch+json'));

        self::assertSame($other->getId(), CustomerGroupQuery::findDefault()->getId());
        self::assertSame(1, CustomerGroupQuery::create()->filterByIsDefault(1)->count());
        self::assertNotSame($previous->getId(), CustomerGroupQuery::findDefault()->getId());
    }

    public function testDeletingAGroupHandsItsCustomersToTheDefaultGroup(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('to-delete');
        $customer = $factory->customer($factory->customerTitle(), ['customerGroup' => $group]);
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('DELETE', '/api/admin/customer_groups/'.$group->getId(), token: $token);

        self::assertSame(204, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull(CustomerGroupQuery::create()->findPk($group->getId()));
        CustomerTableMap::clearInstancePool();
        self::assertSame(CustomerGroupQuery::findDefault()->getId(), CustomerQuery::create()->findPk($customer->getId())->getCustomerGroupId());
    }

    public function testAnAdminWithoutTheGroupRightIsRefusedAndWithItIsServed(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('rights');
        $without = $this->authenticateAsAdmin($factory->restrictedAdmin([AdminResources::CUSTOMER => self::ALL_ACCESSES]));
        $with = $this->authenticateAsAdmin($factory->restrictedAdmin([AdminResources::CUSTOMER_GROUP => self::ALL_ACCESSES]));
        $readOnly = $this->authenticateAsAdmin($factory->restrictedAdmin([AdminResources::CUSTOMER_GROUP => [AccessManager::VIEW]]));

        foreach (['/api/admin/customer_groups', '/api/admin/customer_groups/'.$group->getId(), '/api/admin/customer_group_product_prices'] as $uri) {
            self::assertSame(403, $this->jsonRequest('GET', $uri, token: $without)->getStatusCode(), $uri);
        }
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/customer_groups', ['code' => 'nope'], $without)->getStatusCode());
        self::assertSame(0, CustomerGroupQuery::create()->filterByCode('nope')->count());

        foreach (['/api/admin/customer_groups', '/api/admin/customer_groups/'.$group->getId(), '/api/admin/customer_group_product_prices'] as $uri) {
            self::assertJsonResponseSuccessful($this->jsonRequest('GET', $uri, token: $with));
        }

        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/customer_groups', token: $readOnly));
        self::assertSame(403, $this->jsonRequest('POST', '/api/admin/customer_groups', ['code' => 'nope-ro'], $readOnly)->getStatusCode());
    }

    public function testAGroupPriceIsWrittenAndReadByIriAndItsSlotIsUnique(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('priced');
        $currency = $factory->currency();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency);
        $pse = $product->getProductSaleElementss()->getFirst();
        self::assertNotNull($pse);
        $token = $this->authenticateAsAdmin();

        $payload = [
            'customerGroup' => '/api/admin/customer_groups/'.$group->getId(),
            'productSaleElements' => '/api/admin/product_sale_elements/'.$pse->getId(),
            'currency' => '/api/admin/currencies/'.$currency->getId(),
            'price' => 42.5,
        ];
        $created = $this->decode($this->jsonRequest('POST', '/api/admin/customer_group_product_prices', $payload, $token), 201);

        self::assertEquals(42.5, $created['price']);
        self::assertNull($created['promoPrice'] ?? null);
        $stored = CustomerGroupProductPriceQuery::create()->findPk($created['id']);
        self::assertEquals(42.5, (float) $stored->getPrice());
        self::assertNull($stored->getPromoPrice(), 'no promo price means no price of its own');

        $filtered = $this->decode($this->jsonRequest('GET', '/api/admin/customer_group_product_prices?customerGroup.id='.$group->getId(), token: $token));
        self::assertSame([$created['id']], array_column($filtered['hydra:member'], 'id'));

        $duplicate = $this->jsonRequest('POST', '/api/admin/customer_group_product_prices', $payload, $token);
        self::assertSame(422, $duplicate->getStatusCode(), (string) $duplicate->getContent());

        $patched = $this->decode($this->jsonRequest('PATCH', '/api/admin/customer_group_product_prices/'.$created['id'], ['promoPrice' => 30.0], $token, 'merge-patch+json'));
        self::assertEquals(30.0, $patched['promoPrice']);

        self::assertSame(204, $this->jsonRequest('DELETE', '/api/admin/customer_group_product_prices/'.$created['id'], token: $token)->getStatusCode());
        self::assertNull(CustomerGroupProductPriceQuery::create()->findPk($created['id']));
    }

    /**
     * The group a customer embeds is a summary: its payment modules and categories are read on
     * the single item only, not once more for every row of a list.
     */
    public function testTheCustomersOfAListDoNotReadTheLinksOfTheirGroupOneByOne(): void
    {
        $factory = $this->createFixtureFactory();
        $group = $factory->customerGroup('embedded', paymentModuleIds: [(int) $this->paymentModule('embedded_pay')->getId()], categoryIds: [(int) $factory->category()->getId()]);
        for ($i = 0; $i < 30; ++$i) {
            $factory->customer($factory->customerTitle(), ['customerGroup' => $group]);
        }
        $token = $this->authenticateAsAdmin();

        $statements = $this->recordSqlQueries(fn () => self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/customers?itemsPerPage=30', token: $token)));

        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'customer_group_payment_module'));
        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'customer_group_category'));

        $single = $this->decode($this->jsonRequest('GET', '/api/admin/customer_groups/'.$group->getId(), token: $token));
        self::assertCount(1, $single['paymentModules'], 'The item still carries its links.');
        self::assertCount(1, $single['categories']);
    }

    public function testTheRestrictionFlagsAreReadOnlyAndSetByTheWriteOfTheLists(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $module = $this->paymentModule('flag_pay');
        $token = $this->authenticateAsAdmin();

        // A flag sent with the group is no write: the group stays open.
        $created = $this->decode($this->jsonRequest('POST', '/api/admin/customer_groups', [
            'code' => 'flags',
            'restrictsCategories' => true,
            'restrictsPaymentModules' => true,
        ], $token), 201);
        self::assertFalse($created['restrictsCategories']);
        self::assertFalse($created['restrictsPaymentModules']);

        $written = $this->decode($this->jsonRequest('PATCH', '/api/admin/customer_groups/'.$created['id'], [
            'categories' => ['/api/admin/categories/'.$category->getId()],
            'paymentModules' => ['/api/admin/modules/'.$module->getId()],
        ], $token, 'merge-patch+json'));
        self::assertTrue($written['restrictsCategories']);
        self::assertTrue($written['restrictsPaymentModules']);

        // A write that leaves the lists out leaves the flags, and cannot lift them by hand.
        $left = $this->decode($this->jsonRequest('PATCH', '/api/admin/customer_groups/'.$created['id'], [
            'position' => 9,
            'restrictsCategories' => false,
            'restrictsPaymentModules' => false,
        ], $token, 'merge-patch+json'));
        self::assertTrue($left['restrictsCategories']);
        self::assertTrue($left['restrictsPaymentModules']);

        // The category goes: the list is empty and the flag, read back, still says restricted.
        $category->delete($this->getPropelConnection());
        $read = $this->decode($this->jsonRequest('GET', '/api/admin/customer_groups/'.$created['id'], [], $token));
        self::assertSame([], $read['categories']);
        self::assertTrue($read['restrictsCategories']);

        $lifted = $this->decode($this->jsonRequest('PATCH', '/api/admin/customer_groups/'.$created['id'], ['categories' => []], $token, 'merge-patch+json'));
        self::assertFalse($lifted['restrictsCategories']);
        self::assertTrue($lifted['restrictsPaymentModules'], 'The payment list was not touched.');
    }

    public function testAWriteAnswersWithTheLinksItJustSaved(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $module = $this->paymentModule('written_pay');
        $factory->customerGroup('keeps-the-door-open', categoryIds: [(int) $category->getId()]);
        $token = $this->authenticateAsAdmin();

        $created = $this->decode($this->jsonRequest('POST', '/api/admin/customer_groups', [
            'code' => 'written',
            'paymentModules' => ['/api/admin/modules/'.$module->getId()],
            'categories' => ['/api/admin/categories/'.$category->getId()],
        ], $token), 201);

        self::assertSame(['/api/admin/modules/'.$module->getId()], $created['paymentModules']);
        self::assertSame(['/api/admin/categories/'.$category->getId()], $created['categories']);

        $cleared = $this->decode($this->jsonRequest('PATCH', '/api/admin/customer_groups/'.$created['id'], ['categories' => []], $token, 'merge-patch+json'));

        self::assertSame([], $cleared['categories']);
        self::assertSame(['/api/admin/modules/'.$module->getId()], $cleared['paymentModules'], 'A list left out of the write is left alone.');
    }

    private function paymentModule(string $code): Module
    {
        $module = (new Module())
            ->setCode($code)
            ->setFullNamespace(self::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED);
        $module->save($this->getPropelConnection());

        return $module;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\Symfony\Component\HttpFoundation\Response $response, int $status = 200): array
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
