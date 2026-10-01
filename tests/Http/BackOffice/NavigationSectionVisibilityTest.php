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

use BackOfficeDefaultTwigBundle\Twig\BackOfficeNavigationExtension;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Template\BackOffice\BackOfficeNavigation;
use Thelia\Core\Template\BackOffice\NavigationSectionVoterInterface;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;
use Twig\Environment;
use Twig\Extension\AbstractExtension;

/**
 * A module may hide a section of the back-office navigation (a CMS hides the folders):
 * the theme draws the section only when no navigation voter hides it, on top of the
 * permission to view it.
 */
final class NavigationSectionVisibilityTest extends WebIntegrationTestCase
{
    private const FOLDER_SECTION = '[data-testid="bo-nav-folder"]';

    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever back-office theme
        // it is given, and one older than the navigation voters always draws the section.
        if (!class_exists(BackOfficeNavigationExtension::class)) {
            self::markTestSkipped('The installed back-office theme predates the navigation voters.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);

        $admin = (new FixtureFactory($this->getPropelConnection()))->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }

        parent::tearDown();
    }

    public function testTheFoldersSectionIsDrawnWhenNoModuleHidesIt(): void
    {
        $this->navigateWithVoters();

        self::assertCount(1, $this->requestTheDashboard()->filter(self::FOLDER_SECTION));
    }

    public function testAVoterHidingTheFoldersTakesTheSectionOutOfTheNavigation(): void
    {
        $this->navigateWithVoters(new class implements NavigationSectionVoterInterface {
            public function hidesSection(string $section): bool
            {
                return 'folder' === $section;
            }
        });

        $crawler = $this->requestTheDashboard();

        self::assertCount(0, $crawler->filter(self::FOLDER_SECTION), 'A section a voter hides must not be drawn.');
        self::assertCount(1, $crawler->filter('[data-testid="bo-nav-catalog"]'), 'The other sections must stay.');
    }

    private function requestTheDashboard(): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/home');

        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'The dashboard must be served with a 200.');

        return $crawler;
    }

    /**
     * Voters are collected when the container is compiled, and the kernel is kept for the
     * whole test: the function the navigation calls is registered again, built by the
     * theme's own extension on a navigation holding only the given voters, so the modules
     * active in the test database (a CMS hides the folders) do not weigh on the result.
     * Twig keeps the last definition of a function, and nothing has been rendered yet.
     */
    private function navigateWithVoters(NavigationSectionVoterInterface ...$voters): void
    {
        $navigation = new BackOfficeNavigationExtension(new BackOfficeNavigation($voters));

        /** @var Environment $twig */
        $twig = $this->getService('twig');
        $twig->addExtension(new class($navigation) extends AbstractExtension {
            public function __construct(private readonly BackOfficeNavigationExtension $navigation)
            {
            }

            public function getFunctions(): array
            {
                return $this->navigation->getFunctions();
            }
        });
    }
}
