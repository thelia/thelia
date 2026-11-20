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

namespace Thelia\Tests\Http\Flexy;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Company\CompanyFacade;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\Company;
use Thelia\Model\CompanyRole;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * What the pages of the account show a buyer of a company, as a browser gets them. The pages
 * belong to the theme, which ships as its own package on its own release cycle: a theme older
 * than this feature is reported as skipped rather than failed.
 */
abstract class CompanyAccountTestCase extends WebIntegrationTestCase
{
    /** Written as a string on purpose: `::class` on a missing import resolves silently. */
    private const WITNESS = 'FlexyBundle\\Service\\CompanyAccount';

    protected FixtureFactory $factory;

    protected CompanyFacade $companies;

    private CustomerSessionInjector $injector;

    protected function setUp(): void
    {
        if (!class_exists(self::WITNESS)) {
            self::markTestSkipped('The installed front-office theme has no company account pages.');
        }

        parent::setUp();

        // Not createFixtureFactory(): it leaves a request on the stack, in front of the ones the client sends.
        $this->factory = new FixtureFactory($this->getPropelConnection());
        $this->companies = $this->getService(CompanyFacade::class);
        $this->injector = new CustomerSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);

        // A customer a previous test left in the session of the container is nobody's here.
        $this->getService(Session::class)->remove('thelia.customer_user');

        // The invitation limiters count by address, which is the same for every test: a test that
        // sends a few invitations must not spend what the next one is allowed.
        $limiterPool = $this->getService('cache.rate_limiter');

        if ($limiterPool instanceof CacheItemPoolInterface) {
            $limiterPool->clear();
        }
    }

    protected function tearDown(): void
    {
        // Resistant to the skip above: setUp returned before either was built.
        if (isset($this->injector)) {
            $this->injector->clear();
            $this->getService(Session::class)->remove('thelia.customer_user');
        }

        parent::tearDown();
    }

    /**
     * The customer is put in the session of every request, as a front-office sign-in does, and in the
     * session of the container too: the address services read that one, which a test client does not
     * renew from one request to the next.
     */
    protected function signInAs(?Customer $customer): void
    {
        $session = $this->getService(Session::class);

        if (null === $customer) {
            $this->injector->clear();
            $session->remove('thelia.customer_user');

            return;
        }

        $this->injector->setCustomer($customer);
        $session->set('thelia.customer_user', $customer);
    }

    /**
     * The session holds the cart too, as the one of a signed-in customer is.
     */
    protected function signInWithCart(Customer $customer, Cart $cart): void
    {
        $this->signInAs($customer);
        $this->injector->setCart($cart);
    }

    protected function company(string $name = 'Acme', bool $active = true): Company
    {
        $company = new Company();
        $company->setName($name)->setActive($active ? 1 : 0)->save($this->getPropelConnection());

        return $company;
    }

    protected const PASSWORD = 'Zq7!vK2#mX9$pL4wR';

    protected function customer(?string $email = null): Customer
    {
        $customer = $this->factory->customer($this->factory->customerTitle(), ['password' => self::PASSWORD] + (null === $email ? [] : ['email' => $email]));
        $customer->setEnable(1)->save($this->getPropelConnection());

        return $customer;
    }

    protected function buyer(Company $company, string $roleCode = CompanyRoleCode::BUYER, ?Customer $customer = null): Customer
    {
        $customer ??= $this->customer();
        $this->companies->attach($company, $customer, $roleCode, null, null, null);

        return $customer;
    }

    /**
     * @param list<string> $permissions
     */
    protected function roleOf(string $code, array $permissions): CompanyRole
    {
        $role = new CompanyRole();
        $role->setCode($code)->setPermissions(json_encode($permissions, \JSON_THROW_ON_ERROR))->setLocale('en_US')->setTitle(ucfirst($code));
        $role->save($this->getPropelConnection());

        return $role;
    }

    protected function sharedAddress(Customer $creator, ?Company $company, string $label): Address
    {
        $address = $this->factory->address($creator, null, null, ['label' => $label]);
        $address->setCompanyId($company?->getId())->save($this->getPropelConnection());

        return $address;
    }

    protected function orderPlacedFor(Company $company, Customer $buyer): Order
    {
        $order = $this->factory->order($buyer);
        $order->setCompanyId($company->getId())->setPlacedByCustomerId($buyer->getId())->save($this->getPropelConnection());

        return $order;
    }

    protected function get(string $path): Crawler
    {
        return $this->client->request('GET', $path);
    }

    protected function statusCode(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    /**
     * The token of the first form of the page that carries one.
     */
    protected function tokenOf(Crawler $page): string
    {
        return (string) $page->filter('input[name="_token"]')->first()->attr('value');
    }

    protected function url(string $route, array $parameters = []): string
    {
        return $this->getService(RouterInterface::class)->generate($route, $parameters);
    }

    /**
     * @return list<string>
     */
    protected function subheaderLinks(Crawler $page): array
    {
        return $page->filter('.Subheader-links a')->each(static fn (Crawler $link): string => (string) $link->attr('href'));
    }
}
