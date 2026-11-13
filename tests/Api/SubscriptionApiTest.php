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

namespace Thelia\Tests\Api;

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Subscription\Enum\ActorType;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;
use Thelia\Domain\Subscription\SubscriptionLifecycle;
use Thelia\Model\Map\SubscriptionTableMap;
use Thelia\Model\Sale;
use Thelia\Model\SubscriptionPlanPriceQuery;
use Thelia\Model\SubscriptionPlanQuery;
use Thelia\Model\SubscriptionQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;
use Thelia\Tests\Integration\Domain\Subscription\SubscriptionScenario;
use Thelia\Tests\Support\Payment\RecurringTestPaymentModule;

/**
 * The subscriptions of the account (read, cancel, pause, resume, change of payment method),
 * the subscriptions and the plans of the administration, and the plans the front lists.
 */
final class SubscriptionApiTest extends ApiTestCase
{
    use SubscriptionScenario;

    protected FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->setUpTheSubscriptionShop();
    }

    protected function tearDown(): void
    {
        $this->tearDownTheSubscriptionShop();

        parent::tearDown();
    }

    public function testACustomerReadsTheirSubscriptionWithItsInstallmentsAndNeverTheToken(): void
    {
        [$subscription, , $method] = $this->aSubscription(['Pausable' => 1]);
        $token = $this->authenticateAsCustomer($subscription->getCustomer());

        $response = $this->jsonRequest('GET', '/api/front/account/subscriptions/'.$subscription->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($subscription->getRef(), $data['ref']);
        self::assertSame('active', $data['status']);
        self::assertSame(1, $data['quantity']);
        self::assertSame('month', $data['periodUnit']);
        self::assertSame(9.9, $data['unitPrice']);
        self::assertTrue($data['pausable']);
        self::assertSame('Visa ending 4242', $data['paymentMethodLabel']);
        self::assertSame((int) $method->getId(), $data['paymentMethodId']);
        self::assertCount(1, $data['installments']);
        self::assertSame(1, $data['installments'][0]['number']);
        self::assertSame('paid', $data['installments'][0]['state']);
        self::assertSame($subscription->getInitialOrder()->getRef(), $data['installments'][0]['orderRef']);
        self::assertArrayNotHasKey('cancelAt', $data, 'A null date is left out by the API.');
        self::assertStringNotContainsString((string) $method->getToken(), (string) $response->getContent(), 'The token of the method is never returned.');
    }

    public function testACustomerReadsTheirSubscriptionsAndNoOtherOnes(): void
    {
        [$mine] = $this->aSubscription();
        [$theirs] = $this->aSubscription();
        $token = $this->authenticateAsCustomer($mine->getCustomer());

        $response = $this->jsonRequest('GET', '/api/front/account/subscriptions', token: $token);

        self::assertJsonResponseSuccessful($response);
        $members = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['hydra:member'] ?? [];
        self::assertSame([(int) $mine->getId()], array_column($members, 'id'));
        self::assertArrayNotHasKey('installments', $members[0], 'The installments come with the detail only.');
    }

    public function testAnotherCustomersSubscriptionIsNotFoundWhateverIsAskedOfIt(): void
    {
        [$mine] = $this->aSubscription(['Pausable' => 1]);
        [$theirs] = $this->aSubscription();
        $token = $this->authenticateAsCustomer($mine->getCustomer());

        foreach ([['GET', ''], ['POST', '/cancel'], ['POST', '/pause'], ['POST', '/resume'], ['POST', '/payment_method']] as [$method, $suffix]) {
            self::assertSame(
                404,
                $this->jsonRequest($method, '/api/front/account/subscriptions/'.$theirs->getId().$suffix, $method === 'POST' ? ['paymentMethod' => '/api/front/account/payment_methods/1'] : [], $token)->getStatusCode(),
                \sprintf('%s %s reached the subscription of another customer.', $method, $suffix),
            );
        }

        self::assertSame('active', $this->reloaded($theirs)->getStatus());
    }

    public function testNobodyButACustomerSeesSubscriptions(): void
    {
        [$subscription] = $this->aSubscription();

        self::assertSame(401, $this->jsonRequest('GET', '/api/front/account/subscriptions/'.$subscription->getId())->getStatusCode());
        self::assertSame(403, $this->jsonRequest('GET', '/api/front/account/subscriptions', token: $this->authenticateAsAdmin())->getStatusCode(), 'An administrator reads no subscription as a customer.');
    }

    public function testACustomerPausesResumesAndCancelsTheirSubscription(): void
    {
        [$subscription] = $this->aSubscription(['Pausable' => 1]);
        $token = $this->authenticateAsCustomer($subscription->getCustomer());
        $uri = '/api/front/account/subscriptions/'.$subscription->getId();

        self::assertJsonResponseSuccessful($this->jsonRequest('POST', $uri.'/pause', token: $token));
        self::assertSame(SubscriptionStatus::Paused->value, $this->reloaded($subscription)->getStatus());

        self::assertJsonResponseSuccessful($this->jsonRequest('POST', $uri.'/resume', token: $token));
        self::assertSame(SubscriptionStatus::Active->value, $this->reloaded($subscription)->getStatus());

        $response = $this->jsonRequest('POST', $uri.'/cancel', token: $token);
        self::assertJsonResponseSuccessful($response);
        self::assertSame('cancelled', json_decode((string) $response->getContent(), true)['status']);

        self::assertSame(409, $this->jsonRequest('POST', $uri.'/pause', token: $token)->getStatusCode(), 'What the state refuses is a conflict.');
        self::assertSame(409, $this->jsonRequest('POST', $uri.'/cancel', token: $token)->getStatusCode());
    }

    /**
     * Resuming undoes a pause, nothing else: a suspended subscription is brought back by paying
     * what is due, and a trial ends with its first charge.
     */
    public function testACustomerResumesOnlyAPausedSubscription(): void
    {
        [$subscription] = $this->aSubscription(['Pausable' => 1]);
        $token = $this->authenticateAsCustomer($subscription->getCustomer());
        $uri = '/api/front/account/subscriptions/'.$subscription->getId().'/resume';

        foreach ([SubscriptionStatus::Active, SubscriptionStatus::Trial, SubscriptionStatus::PaymentFailed, SubscriptionStatus::Suspended] as $status) {
            SubscriptionQuery::create()->filterById($subscription->getId())->update(['Status' => $status->value, 'NextBillingAt' => new \DateTime('2028-02-29 10:00:00')], $this->getPropelConnection());
            SubscriptionTableMap::clearInstancePool();

            self::assertSame(409, $this->jsonRequest('POST', $uri, token: $token)->getStatusCode(), 'A subscription that is '.$status->value.' is not resumed.');
            SubscriptionTableMap::clearInstancePool();
            $reloaded = $this->reloaded($subscription);
            self::assertSame($status->value, $reloaded->getStatus());
            self::assertSame('2028-02-29 10:00:00', $reloaded->getNextBillingAt('Y-m-d H:i:s'), 'Its schedule is left as it was.');
        }

        self::assertSame([], RecurringTestPaymentModule::$charges, 'Nothing is charged by a refused resume.');
    }

    public function testACustomerPausesOnlyWhatThePlanLetsThemPause(): void
    {
        [$subscription] = $this->aSubscription();
        $token = $this->authenticateAsCustomer($subscription->getCustomer());

        self::assertSame(409, $this->jsonRequest('POST', '/api/front/account/subscriptions/'.$subscription->getId().'/pause', token: $token)->getStatusCode());
    }

    public function testACustomerChangesTheMethodOfTheirSubscriptionToOneOfTheirOwn(): void
    {
        [$subscription, , $method] = $this->aSubscription();
        $other = $this->recurringMethodOf($subscription->getCustomer(), $this->recurringModule, $this->getPropelConnection());
        $strangers = $this->recurringMethodOf($this->aCustomer(), $this->recurringModule, $this->getPropelConnection());
        $token = $this->authenticateAsCustomer($subscription->getCustomer());
        $uri = '/api/front/account/subscriptions/'.$subscription->getId().'/payment_method';

        self::assertSame(422, $this->jsonRequest('POST', $uri, ['paymentMethod' => '/api/front/account/payment_methods/'.$strangers->getId()], $token)->getStatusCode(), 'Not their method.');
        self::assertSame((int) $method->getId(), (int) $this->reloaded($subscription)->getCustomerPaymentMethodId());

        $response = $this->jsonRequest('POST', $uri, ['paymentMethod' => '/api/front/account/payment_methods/'.$other->getId()], $token);

        self::assertJsonResponseSuccessful($response);
        self::assertSame((int) $other->getId(), (int) $this->reloaded($subscription)->getCustomerPaymentMethodId());
    }

    public function testTheAdministrationListsTheSubscriptionsByStatus(): void
    {
        [$active] = $this->aSubscription();
        [$cancelled] = $this->aSubscription();
        $this->getService(SubscriptionLifecycle::class)->cancel($cancelled, ActorType::Admin, 1);
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('GET', '/api/admin/subscriptions?status=cancelled', token: $token);

        self::assertJsonResponseSuccessful($response);
        $ids = array_column(json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['hydra:member'] ?? [], 'id');
        self::assertContains((int) $cancelled->getId(), $ids);
        self::assertNotContains((int) $active->getId(), $ids);
    }

    public function testAnAdminWithoutTheSubscriptionPermissionIsRefused(): void
    {
        [$subscription] = $this->aSubscription();
        $token = $this->authenticateAsAdmin($this->factory->restrictedAdmin([AdminResources::ORDER => [AccessManager::VIEW]]));

        foreach (['/api/admin/subscriptions', '/api/admin/subscriptions/'.$subscription->getId(), '/api/admin/subscription_plans'] as $uri) {
            self::assertSame(403, $this->jsonRequest('GET', $uri, token: $token)->getStatusCode(), $uri);
        }

        $allowed = $this->authenticateAsAdmin($this->factory->restrictedAdmin([AdminResources::SUBSCRIPTION => [AccessManager::VIEW]]));
        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/subscriptions', token: $allowed));
    }

    public function testTheAdministrationCreatesAPlanWithItsPricesAndTitles(): void
    {
        $product = $this->aProduct();
        $currency = $this->factory->currency();
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/admin/subscription_plans', [
            'product' => '/api/admin/products/'.$product->getId(),
            'periodUnit' => 'month',
            'periodCount' => 1,
            'trialDays' => 7,
            'pausable' => true,
            'visible' => true,
            'i18ns' => ['en_US' => ['title' => 'Monthly box']],
            'prices' => [['currencyId' => $currency->getId(), 'price' => 12.5, 'fromDefaultCurrency' => false]],
        ], $token);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $plan = SubscriptionPlanQuery::create()->findPk($data['id']);
        self::assertNotNull($plan);
        self::assertSame((int) $product->getId(), (int) $plan->getProductId());
        self::assertSame(7, (int) $plan->getTrialDays());
        self::assertSame('12.500000', (string) SubscriptionPlanPriceQuery::create()->filterBySubscriptionPlanId($plan->getId())->findOne()->getPrice());
        self::assertSame(12.5, $data['prices'][0]['price']);

        // Rewriting the prices replaces them.
        $this->jsonRequest('PATCH', '/api/admin/subscription_plans/'.$plan->getId(), ['prices' => [['currencyId' => $currency->getId(), 'price' => 15]]], $token, 'merge-patch+json');
        self::assertSame('15.000000', (string) SubscriptionPlanPriceQuery::create()->filterBySubscriptionPlanId($plan->getId())->findOne()->getPrice());
    }

    public function testTheFrontListsTheVisiblePlansOfAProductWithTheirPrice(): void
    {
        $product = $this->aProduct();
        $visible = $this->aPlan($product, ['TrialDays' => 14, 'CommitmentCount' => 3], '9.900000');
        $hidden = $this->aPlan($product, ['Visible' => 0], '5.000000');
        $otherProduct = $this->aProduct();
        $this->aPlan($otherProduct);

        $response = $this->jsonRequest('GET', '/api/front/subscription_plans?product.id='.$product->getId());

        self::assertJsonResponseSuccessful($response);
        $members = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['hydra:member'] ?? [];
        self::assertSame([(int) $visible->getId()], array_column($members, 'id'));
        self::assertSame(9.9, $members[0]['price']);
        self::assertSame($this->factory->currency()->getCode(), $members[0]['currencyCode']);
        self::assertSame(14, $members[0]['trialDays']);
        self::assertSame(3, $members[0]['commitmentCount']);
        self::assertSame('Monthly box', $members[0]['i18ns']['en_US']['title'] ?? null);
        self::assertArrayNotHasKey('installmentCount', $members[0], 'A plan with no end leaves it out.');
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/subscription_plans/'.$hidden->getId())->getStatusCode(), 'A plan turned off is not served by id either.');
    }

    public function testTheFrontDoesNotServeThePlansOfAProductThatIsOffline(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($product);
        $product->setVisible(0)->save($this->getPropelConnection());

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/subscription_plans/'.$plan->getId())->getStatusCode());
    }

    public function testTheFrontServesThePlansOfAProductOfAPrivateDropToItsGuestsOnly(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($product);
        $guest = $this->aCustomer();
        $sale = $this->factory->sale([
            'active' => true,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => true,
        ]);
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $guest);

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/products/'.$product->getId())->getStatusCode(), 'The product itself is hidden.');
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/subscription_plans/'.$plan->getId())->getStatusCode(), 'An anonymous visitor does not read the plan.');
        self::assertSame([], $this->planIdsOf('/api/front/subscription_plans?product.id='.$product->getId()), 'Nor finds it in the list.');

        $token = $this->authenticateAsCustomer($guest);
        self::assertSame(200, $this->jsonRequest('GET', '/api/front/subscription_plans/'.$plan->getId(), token: $token)->getStatusCode(), 'A guest of the drop reads it.');
        self::assertSame([(int) $plan->getId()], $this->planIdsOf('/api/front/subscription_plans?product.id='.$product->getId(), $token));
    }

    public function testTheFrontDoesNotServeThePlansOfAProductOfACategoryClosedToTheGroup(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($product);
        $group = $this->factory->customerGroup('pro', categoryIds: [(int) $this->factory->category()->getId()]);
        $this->getService('services_resetter')->reset();
        $token = $this->authenticateAsCustomer($this->factory->customer($this->factory->customerTitle(), ['password' => 'password', 'customerGroup' => $group]));

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/products/'.$product->getId(), token: $token)->getStatusCode(), 'The product itself is hidden from the group.');
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/subscription_plans/'.$plan->getId(), token: $token)->getStatusCode());
        self::assertSame([], $this->planIdsOf('/api/front/subscription_plans?product.id='.$product->getId(), $token));
        self::assertSame([(int) $plan->getId()], $this->planIdsOf('/api/front/subscription_plans?product.id='.$product->getId()), 'The default group still reads it.');
    }

    public function testTheFrontGivesThePriceOfAPlanToWhoeverIsShownThePriceOfItsProduct(): void
    {
        $product = $this->aProduct();
        $plan = $this->aPlan($product);
        $product->setPriceDisplayMode('logged_in')->save($this->getPropelConnection());
        $this->getService('services_resetter')->reset();

        $anonymous = json_decode((string) $this->jsonRequest('GET', '/api/front/subscription_plans/'.$plan->getId())->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame((int) $plan->getId(), $anonymous['id'], 'The plan is still described.');
        self::assertArrayNotHasKey('price', $anonymous, 'Without its price, as the product shows none to an anonymous visitor.');

        $token = $this->authenticateAsCustomer($this->aCustomer());
        $signedIn = json_decode((string) $this->jsonRequest('GET', '/api/front/subscription_plans/'.$plan->getId(), token: $token)->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(9.9, $signedIn['price'] ?? null);
    }

    /**
     * @return list<int>
     */
    private function planIdsOf(string $uri, ?string $token = null): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $token);
        self::assertJsonResponseSuccessful($response);

        return array_map('intval', array_column(json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['hydra:member'] ?? [], 'id'));
    }
}
