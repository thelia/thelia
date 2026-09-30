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

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;
use Thelia\Test\WebIntegrationTestCase;

final class AdministratorDeletionCeilingTest extends WebIntegrationTestCase
{
    private const PASSWORD = 'correct horse battery';

    protected function setUp(): void
    {
        parent::setUp();

        $adminTemplate = $this->getService(TemplateHelperInterface::class)->getActiveAdminTemplate();

        if (!file_exists($adminTemplate->getAbsolutePath().\DIRECTORY_SEPARATOR.'configuration/account/two-factor.html.twig')) {
            self::markTestSkipped('The installed back-office theme does not guard the superadministrator accounts yet.');
        }
    }

    public function testARestrictedAdministratorDeletesAnAccountWithinTheirCeiling(): void
    {
        $colleague = $this->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]);
        $this->signIn($this->restrictedAdmin([AdminResources::ADMINISTRATOR => [AccessManager::VIEW, AccessManager::DELETE]]));

        $list = $this->request('GET', '/admin/configuration/administrators');
        self::assertCount(1, $list->filter('[data-administrator-id="'.$colleague->getId().'"][data-bs-target="#administrator-delete-modal"]'));

        $this->delete($list, $colleague);

        self::assertNull(AdminQuery::create()->findPk($colleague->getId()));
    }

    public function testARestrictedAdministratorCannotDeleteASuperadministrator(): void
    {
        $superadministrator = $this->createFixtureFactory()->admin(['password' => self::PASSWORD]);
        self::assertNull($superadministrator->getProfileId());
        $this->signIn($this->restrictedAdmin([AdminResources::ADMINISTRATOR => [AccessManager::VIEW, AccessManager::DELETE]]));

        $list = $this->request('GET', '/admin/configuration/administrators');
        self::assertCount(0, $list->filter('[data-administrator-id="'.$superadministrator->getId().'"][data-bs-target="#administrator-delete-modal"]'));

        $this->delete($list, $superadministrator);

        self::assertResponseRedirects('/admin/configuration/administrators');
        self::assertNotNull(AdminQuery::create()->findPk($superadministrator->getId()));
        $afterRefusal = $this->request('GET', '/admin/configuration/administrators');
        self::assertStringContainsString('Only a superadministrator can delete a superadministrator account.', $afterRefusal->filter('.alert-danger')->text(''));
    }

    /**
     * @param array<string, list<string>> $rights
     */
    private function restrictedAdmin(array $rights): Admin
    {
        return $this->createFixtureFactory()->restrictedAdmin($rights, ['password' => self::PASSWORD]);
    }

    private function delete(Crawler $list, Admin $target): void
    {
        $form = $list->filter('[data-testid="administrator-delete-form"]');

        $this->request('POST', (string) $form->attr('action'), [
            'administrator_id' => $target->getId(),
            '_token' => (string) $form->filter('input[name="_token"]')->attr('value'),
        ]);
    }

    private function request(string $method, string $uri, array $parameters = []): Crawler
    {
        $requestStack = $this->getService(RequestStack::class);

        while (($request = $requestStack->getCurrentRequest()) instanceof Request && !$request->hasSession()) {
            $requestStack->pop();
        }

        return $this->client->request($method, $uri, $parameters);
    }

    private function signIn(Admin $admin): void
    {
        $login = $this->request('GET', '/admin/login');

        $this->request('POST', '/admin/checklogin', [
            'thelia_admin_login' => [
                'username' => $admin->getLogin(),
                'password' => self::PASSWORD,
                'success_url' => '/admin',
                '_token' => (string) $login->filter('input[name="thelia_admin_login[_token]"]')->attr('value'),
            ],
        ]);
    }
}
