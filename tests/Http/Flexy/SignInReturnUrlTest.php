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

use Thelia\Domain\Customer\Service\AuthenticationReturnUrl;
use Thelia\Model\Customer;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Twig\Environment;

/**
 * Signing in interrupts what a visitor was doing, and they are meant to come back to it.
 *
 * The page they were on travels as a query parameter of the sign-in links, the form posts
 * it back, and the session remembers it for as long as the journey takes. What a visitor
 * writes in that parameter is checked against the shop host, so it cannot be used to
 * bounce someone off the shop (CWE-601).
 */
final class SignInReturnUrlTest extends WebIntegrationTestCase
{
    private const PASSWORD = 'a-password-of-a-real-account';

    /**
     * A theme older than this feature carries none of it, and the core ships with whichever
     * theme version it is given: reported as a skip rather than a failure. The Twig function
     * the sign-in links call is what the templates and the controller were changed together
     * with, so its presence answers for the whole of it.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (null === $this->getService(Environment::class)->getFunction('sign_in_path')) {
            self::markTestSkipped('The installed front-office theme does not carry the return to the interrupted page yet.');
        }
    }

    public function testTheSignInLinkOfAPageNamesThatPage(): void
    {
        $crawler = $this->client->request('GET', '/contact');

        self::assertNotSame(
            0,
            $crawler->filter('a[href*="'.AuthenticationReturnUrl::PARAMETER.'=%2Fcontact"], a[href*="'.AuthenticationReturnUrl::PARAMETER.'=/contact"]')->count(),
            'The header sign-in link must carry the page it is displayed on.',
        );
    }

    public function testAVisitorComesBackToThePageTheySignedInFrom(): void
    {
        $account = $this->enabledAccount();

        $this->submitLoginForm('/customer/login?'.AuthenticationReturnUrl::PARAMETER.'=/contact', $account);

        self::assertStringEndsWith('/contact', $this->redirectionTarget());
    }

    public function testAReturnUrlLeavingTheShopIsIgnored(): void
    {
        $account = $this->enabledAccount();

        $this->submitLoginForm(
            '/customer/login?'.AuthenticationReturnUrl::PARAMETER.'='.urlencode('https://evil.example.net/pwn'),
            $account,
        );

        self::assertStringEndsWith('/account', $this->redirectionTarget());
    }

    public function testTheDestinationSurvivesAWrongPassword(): void
    {
        $account = $this->enabledAccount();
        $signInPage = '/customer/login?'.AuthenticationReturnUrl::PARAMETER.'=/contact';

        $this->submitLoginForm($signInPage, $account, 'not-the-password');

        // Second attempt from the bare sign-in page: the parameter is gone from the URL,
        // and the session is what still knows where the visitor was going.
        $this->submitLoginForm('/customer/login', $account);

        self::assertStringEndsWith('/contact', $this->redirectionTarget());
    }

    public function testAGuardedPageIsNamedInTheRedirectionToTheSignInPage(): void
    {
        $this->client->request('GET', '/account/orders');

        self::assertStringContainsString(
            AuthenticationReturnUrl::PARAMETER.'=',
            $this->redirectionLocation(),
            'A page behind the login must be named in the redirection, to be given back after the sign-in.',
        );
    }

    public function testRegisteringSignsTheNewCustomerIn(): void
    {
        $crawler = $this->client->request('GET', '/customer/register');
        $form = $crawler->filter('form[name="flexybundle_form_customer_register_form"]')->form();

        $form['flexybundle_form_customer_register_form[firstname]'] = 'Jane';
        $form['flexybundle_form_customer_register_form[lastname]'] = 'Doe';
        $form['flexybundle_form_customer_register_form[email]'] = 'signed-in-on-registration@example.com';
        $form['flexybundle_form_customer_register_form[password]'] = self::PASSWORD;

        $this->client->submit($form);

        // The account pages answer 200 only to a session that holds an account: before the
        // registration signed the new customer in, this bounced back to the login form.
        $this->client->request('GET', '/account');

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'The first registration step must sign the new customer in, as nothing is left to check.',
        );
    }

    private function submitLoginForm(string $signInPage, Customer $account, ?string $password = null): void
    {
        $crawler = $this->client->request('GET', $signInPage);
        $form = $crawler->filter('form[name="thelia_customer_login"]')->form();

        $form['thelia_customer_login[email]'] = (string) $account->getEmail();
        $form['thelia_customer_login[password]'] = $password ?? self::PASSWORD;
        $form['thelia_customer_login[account]'] = '1';

        $this->client->submit($form);
    }

    /**
     * The path alone: the destination is what these assertions are about, not the query
     * string a redirection to the sign-in page carries.
     */
    private function redirectionTarget(): string
    {
        $location = $this->redirectionLocation();

        return parse_url($location, \PHP_URL_PATH) ?: $location;
    }

    private function redirectionLocation(): string
    {
        $response = $this->client->getResponse();

        self::assertTrue($response->isRedirect(), 'The request must answer with a redirect.');

        return (string) $response->headers->get('Location');
    }

    private function enabledAccount(): Customer
    {
        $fixtures = new FixtureFactory($this->getPropelConnection());
        $account = $fixtures->customer($fixtures->customerTitle(), ['password' => self::PASSWORD]);

        // Fixtures come out disabled, and an unconfirmed account is sent to the activation
        // page instead of signing in.
        $account->setEnable(1)->save($this->getPropelConnection());

        return $account;
    }
}
