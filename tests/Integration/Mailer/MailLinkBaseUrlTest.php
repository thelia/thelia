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

namespace Thelia\Tests\Integration\Mailer;

use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\Email;
use Thelia\Mailer\MailerFactory;
use Thelia\Mailer\MailLinkBaseUrl;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\LangQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tools\URL;

/**
 * The link of a password reset mail leads to the shop, whatever host the request that
 * sent it was addressed to.
 */
final class MailLinkBaseUrlTest extends IntegrationTestCase
{
    private const SHOP_URL = 'https://shop.example';

    private FixtureFactory $factory;

    private ?string $previousHost = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        // The URL singleton may still be the one of the kernel of an earlier test, which
        // reads the request stack of that kernel: building the service of this one takes over.
        $this->getService(URL::class);
        $this->previousHost = $this->mainRequest()->headers->get('host');
    }

    protected function tearDown(): void
    {
        $this->mainRequest()->headers->set('host', $this->previousHost);
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheResetLinkLeadsToTheConfiguredShopWhateverTheHostOfTheRequest(): void
    {
        ConfigQuery::write('url_site', self::SHOP_URL);
        $this->mainRequest()->headers->set('host', 'elsewhere.example');

        $email = $this->renderLostPasswordMail('reset-token-1');

        foreach ([$email->getHtmlBody(), $email->getTextBody()] as $body) {
            self::assertStringContainsString(self::SHOP_URL.'/password/reset/reset-token-1', (string) $body);
            self::assertStringNotContainsString('elsewhere.example', (string) $body);
        }
    }

    public function testAHostTheShopDeclaresIsKept(): void
    {
        $lang = LangQuery::create()->findOneByLocale('en_US');
        $lang->setUrl('https://en.shop.example')->save();
        ConfigQuery::write('url_site', self::SHOP_URL);
        ConfigQuery::write('one_domain_foreach_lang', '1');
        $this->mainRequest()->headers->set('host', 'en.shop.example');

        $email = $this->renderLostPasswordMail('reset-token-2');

        self::assertStringContainsString('://en.shop.example/password/reset/reset-token-2', (string) $email->getHtmlBody());
    }

    public function testWithoutAConfiguredAddressTheLinkFollowsTheRequest(): void
    {
        ConfigQuery::write('url_site', '');
        $this->mainRequest()->headers->set('host', 'unconfigured.example');

        $email = $this->renderLostPasswordMail('reset-token-3');

        self::assertStringContainsString('://unconfigured.example/password/reset/reset-token-3', (string) $email->getHtmlBody());
    }

    public function testWithoutAShopAddressTheLinksLeadToTheDefaultUri(): void
    {
        ConfigQuery::write('url_site', '');

        $baseUrl = (new MailLinkBaseUrl(new NullLogger(), self::SHOP_URL.'/'))
            ->resolve(Request::create('http://elsewhere.example/'), 'en_US');

        self::assertSame(self::SHOP_URL, $baseUrl);
    }

    public function testWithoutAShopAddressTheHostOfTheDefaultUriIsKept(): void
    {
        ConfigQuery::write('url_site', '');

        $baseUrl = (new MailLinkBaseUrl(new NullLogger(), self::SHOP_URL))
            ->resolve(Request::create('http://shop.example/'), 'en_US');

        self::assertNull($baseUrl);
    }

    public function testADefaultUriOnTheMachineItselfIsNotAShopAddress(): void
    {
        ConfigQuery::write('url_site', '');

        $baseUrl = (new MailLinkBaseUrl(new NullLogger(), 'http://localhost'))
            ->resolve(Request::create('http://unconfigured.example/'), 'en_US');

        self::assertNull($baseUrl);
    }

    public function testTheShopAddressComesBeforeTheDefaultUri(): void
    {
        ConfigQuery::write('url_site', self::SHOP_URL);

        $baseUrl = (new MailLinkBaseUrl(new NullLogger(), 'https://other.example'))
            ->resolve(Request::create('http://other.example/'), 'en_US');

        self::assertSame(self::SHOP_URL, $baseUrl);
    }

    private function renderLostPasswordMail(string $token): Email
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        \assert($customer instanceof Customer);

        $email = $this->getService(MailerFactory::class)->createEmailMessage(
            'lost_password',
            ['shop@example.com' => 'Shop'],
            [(string) $customer->getEmail() => 'Customer'],
            ['token' => $token, 'tokenLifetimeInMinutes' => 60, 'customer_id' => $customer->getId()],
            'en_US',
        );

        return $email;
    }

    private function mainRequest(): Request
    {
        $request = $this->getService(RequestStack::class)->getMainRequest();
        self::assertNotNull($request);

        return $request;
    }
}
