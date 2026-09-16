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

use Thelia\Model\Admin;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The writing direction of the back-office, seen from the served page.
 *
 * The base template of the default-twig back-office writes `lang_direction()` into the
 * dir attribute of its root element, and the direction follows the working language of
 * the administrator - the same language the interface is translated into.
 *
 * The page asked for below is rendered by a back-office controller through the Twig
 * environment, not through the Thelia template parser: the parser assigns its
 * `lang_direction` variable on its own renders only, and nothing assigns it here. This
 * is the case that proves the function - registered on the environment by the TwigEngine
 * module - reaches an ordinary administration screen where the variable never would.
 */
final class AdminWritingDirectionTest extends WebIntegrationTestCase
{
    private const PASSWORD = 'password';

    public function testAnAdministratorWorkingInALeftToRightLanguageIsServedALeftToRightPage(): void
    {
        $this->skipUnlessTwigBackOffice();

        $this->logInAs($this->newAdmin('fr_FR'));

        self::assertSame('ltr', $this->directionOfRenderedPage());
    }

    public function testAnAdministratorWorkingInARightToLeftLanguageIsServedARightToLeftPage(): void
    {
        $this->skipUnlessTwigBackOffice();

        $this->rightToLeftLanguage();
        $this->logInAs($this->newAdmin('ar_SA'));

        self::assertSame('rtl', $this->directionOfRenderedPage());
    }

    /**
     * An installation whose Arabic language is not installed is the normal case, so the
     * language is created rather than expected - the transaction of the test case rolls
     * it back.
     */
    private function rightToLeftLanguage(): Lang
    {
        $existing = LangQuery::create()->filterByLocale('ar_SA')->findOne($this->getPropelConnection());

        if ($existing instanceof Lang) {
            return $existing;
        }

        return (new FixtureFactory($this->getPropelConnection()))->lang([
            'title' => 'العربية',
            'code' => 'ar',
            'locale' => 'ar_SA',
            'active' => true,
        ]);
    }

    private function directionOfRenderedPage(): string
    {
        $this->client->request('GET', '/admin/sales');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $body = (string) $this->client->getResponse()->getContent();

        self::assertMatchesRegularExpression(
            '/<html[^>]*\sdir="(ltr|rtl)"/',
            $body,
            'The root element of a back-office page must carry a writing direction',
        );

        preg_match('/<html[^>]*\sdir="(ltr|rtl)"/', $body, $matches);

        return $matches[1];
    }

    private function newAdmin(string $locale): Admin
    {
        return (new FixtureFactory($this->getPropelConnection()))
            ->admin(['locale' => $locale, 'password' => self::PASSWORD]);
    }

    /**
     * Signs in through the real login form, so that the language of the session is
     * decided by the login itself and not by an injected session.
     */
    private function logInAs(Admin $admin): void
    {
        $crawler = $this->client->request('GET', '/admin/login');
        $token = $crawler->filter('input[name="thelia_admin_login[_token]"]');

        self::assertGreaterThan(0, $token->count(), 'The login form must carry a CSRF token');

        $this->client->request('POST', '/admin/checklogin', [
            'thelia_admin_login' => [
                'username' => $admin->getLogin(),
                'password' => self::PASSWORD,
                'success_url' => '/admin',
                '_token' => (string) $token->attr('value'),
            ],
        ]);
    }

    /**
     * The dir attribute asserted here is written by the base template of the
     * default-twig back-office. A project running another back-office template has no
     * such template, and nothing to assert.
     */
    private function skipUnlessTwigBackOffice(): void
    {
        $template = static::getContainer()->getParameter('thelia_admin_template');

        if ('default-twig' !== $template) {
            self::markTestSkipped(\sprintf(
                'The installed back-office template is "%s", not "default-twig".',
                \is_string($template) ? $template : 'unknown',
            ));
        }

        // The suite runs against the template the checkout installed, which on CI is the
        // published package rather than the working copy. One that predates the writing
        // direction never writes the attribute, and has nothing to assert.
        $shell = THELIA_TEMPLATE_DIR.'backOffice'.\DIRECTORY_SEPARATOR.$template.\DIRECTORY_SEPARATOR.'base.html.twig';

        if (!is_file($shell) || !str_contains((string) file_get_contents($shell), 'lang_direction')) {
            self::markTestSkipped('The installed back-office template predates the writing direction.');
        }
    }
}
