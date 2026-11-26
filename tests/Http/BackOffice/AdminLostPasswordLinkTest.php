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

namespace Thelia\Tests\Http\BackOffice;

use Symfony\Component\Mime\Email;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * An administrator who lost their password is mailed a link to choose a new one. The
 * link leads to the shop the administrator set up, whatever host the page that asked
 * for it was addressed to.
 */
final class AdminLostPasswordLinkTest extends WebIntegrationTestCase
{
    private const SHOP_URL = 'https://shop.example';

    protected function setUp(): void
    {
        parent::setUp();

        if (ConfigQuery::read('active-admin-template') !== 'default-twig') {
            self::markTestSkipped('The Twig back-office is not the active admin template of the test shop.');
        }

        $templatePath = $this->getService(TemplateHelperInterface::class)->getActiveMailTemplate()->getAbsolutePath().DS.'admin_password.html.twig';

        if (!is_file($templatePath)) {
            self::markTestSkipped('The installed mail template has no administrator password e-mail.');
        }
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheLinkLeadsToTheConfiguredShopWhateverTheHostOfTheRequest(): void
    {
        $admin = (new FixtureFactory($this->getPropelConnection()))->admin();

        $crawler = $this->client->request('GET', 'http://elsewhere.example/admin/lost-password');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        // After the first request: a configuration written before it loses the session.
        ConfigQuery::write('url_site', self::SHOP_URL);
        ConfigQuery::write('store_email', 'shop@example.com');
        ConfigQuery::resetCache();

        $field = $crawler->filter('input[name*="username_or_email"]');
        $form = $crawler->filter('form')
            ->reduce(static fn ($form): bool => $form->filter('input[name*="username_or_email"]')->count() > 0)
            ->form([(string) $field->attr('name') => (string) $admin->getEmail()]);
        $this->client->submit($form);

        $mails = array_values(array_filter(
            self::getMailerMessages(),
            static fn (object $message): bool => $message instanceof Email
                && $message->getTo()[0]->getAddress() === $admin->getEmail(),
        ));
        self::assertCount(1, $mails, 'The administrator must be mailed a link.');

        foreach ([$mails[0]->getHtmlBody(), $mails[0]->getTextBody()] as $body) {
            self::assertMatchesRegularExpression('#'.preg_quote(self::SHOP_URL, '#').'/admin/password-create/[^"\s<]+#', (string) $body);
            self::assertStringNotContainsString('elsewhere.example', (string) $body);
        }
    }
}
