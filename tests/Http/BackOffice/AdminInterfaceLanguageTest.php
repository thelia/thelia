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

use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Admin;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The interface language of the back-office: the one the language switcher of the
 * top bar picks, carried by a "lang" query parameter, resolved by
 * {@see \Thelia\Domain\Localization\Service\LangService::handleLang()} and stored
 * in the session as the admin language.
 *
 * Most of what is asserted here is decided at kernel.request and written to the
 * session and to the admin row, before any template is involved: it holds for
 * every back-office template. The one test that reads a rendered page names the
 * template it needs and skips otherwise.
 *
 * The edition language of the contents (the flag row of an edition screen, read
 * from "edit_language_id") is a different thing and is left untouched.
 */
final class AdminInterfaceLanguageTest extends WebIntegrationTestCase
{
    private const PASSWORD = 'password';

    private AdminSessionInjector $injector;

    private ?Session $lastSession = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->injector = new AdminSessionInjector();

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($this->injector);

        // The KernelBrowser hands back a Request with no session attached, so the
        // session of the request that was just handled is caught on its way out.
        // kernel.response and not kernel.request: the login controller decides the
        // language of a fresh session itself, long after kernel.request is over.
        if ($dispatcher instanceof SymfonyEventDispatcherInterface) {
            $dispatcher->addListener(
                KernelEvents::RESPONSE,
                function (ResponseEvent $event): void {
                    $request = $event->getRequest();

                    if ($request->hasSession()) {
                        $session = $request->getSession();
                        $this->lastSession = $session instanceof Session ? $session : null;
                    }
                },
                -1024,
            );
        }
    }

    protected function tearDown(): void
    {
        // A test skipped before setUp() completed leaves no injector behind.
        if (isset($this->injector)) {
            $this->injector->clear();
        }

        parent::tearDown();
    }

    public function testSwitchingTheInterfaceLanguageStoresItInTheSession(): void
    {
        $admin = $this->injectAdminSession('en_US');

        $this->client->request('GET', '/admin/home?lang=fr');

        self::assertSame('fr_FR', $this->sessionAdminLocale());

        // The choice outlives the request that made it: the next page carries no
        // parameter and must still be served in the chosen language.
        $this->client->request('GET', '/admin/home');

        self::assertSame('fr_FR', $this->sessionAdminLocale());
        self::assertSame('fr_FR', $this->storedLocaleOf($admin->getId()));
    }

    public function testSwitchingTheInterfaceLanguagePersistsItOnTheAdmin(): void
    {
        $admin = $this->injectAdminSession('en_US');

        $this->client->request('GET', '/admin/home?lang=fr');

        self::assertSame(
            'fr_FR',
            $this->storedLocaleOf($admin->getId()),
            'The interface language must be written to admin.locale so it survives a logout',
        );
    }

    public function testAnInterfaceLanguageAskedForByLocaleIsAcceptedToo(): void
    {
        $admin = $this->injectAdminSession('en_US');

        $this->client->request('GET', '/admin/home?lang=es_ES');

        self::assertSame('es_ES', $this->sessionAdminLocale());
        self::assertSame('es_ES', $this->storedLocaleOf($admin->getId()));
    }

    public function testAPlainPageViewLeavesTheStoredInterfaceLanguageAlone(): void
    {
        $admin = $this->injectAdminSession('it_IT');

        $this->client->request('GET', '/admin/home');

        self::assertSame('it_IT', $this->storedLocaleOf($admin->getId()));
    }

    public function testLoggingInAdoptsTheInterfaceLanguageStoredOnTheAdmin(): void
    {
        $this->logInAs($this->newAdmin('fr_FR'));

        self::assertSame(
            'fr_FR',
            $this->sessionAdminLocale(),
            'A fresh session must take its interface language from admin.locale',
        );
    }

    /**
     * The whole point, seen from the page: an administrator whose stored interface
     * language is French signs in and is served a French back-office, with no
     * language parameter anywhere in the url.
     */
    public function testAPageIsRenderedInTheStoredInterfaceLanguageAfterLoggingIn(): void
    {
        $this->skipUnlessTwigBackOffice();

        $this->logInAs($this->newAdmin('fr_FR'));

        $this->client->request('GET', '/admin/sales');

        $body = (string) $this->client->getResponse()->getContent();

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            'Nouvelle promotion',
            $body,
            'The back-office chrome must be rendered in the interface language of the administrator',
        );
    }

    private function newAdmin(string $locale): Admin
    {
        $factory = new FixtureFactory($this->getPropelConnection());

        return $factory->admin(['locale' => $locale, 'password' => self::PASSWORD]);
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
     * The French strings asserted below belong to the catalogues of the default-twig
     * back-office template. A project running another back-office template has no
     * such catalogue, and nothing to assert.
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
    }

    private function injectAdminSession(string $locale): Admin
    {
        // Built without createFixtureFactory(): that helper pushes a synthetic
        // request when the stack is empty, and it would then be the "main"
        // request of the calls below — the one the session is read from.
        $factory = new FixtureFactory($this->getPropelConnection());

        $admin = $factory->admin(['locale' => $locale]);
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);

        return $admin;
    }

    private function sessionAdminLocale(): string
    {
        self::assertInstanceOf(Session::class, $this->lastSession, 'No session was carried by the last request');

        return $this->lastSession->getAdminLang()->getLocale();
    }

    /**
     * Read straight from the connection: the Propel instance pool holds the very
     * object the request wrote to, so asking it would prove nothing about what
     * reached the database.
     */
    private function storedLocaleOf(int $adminId): string
    {
        $statement = $this->getPropelConnection()->prepare('SELECT locale FROM admin WHERE id = :id');
        $statement->execute(['id' => $adminId]);

        return (string) $statement->fetchColumn();
    }
}
