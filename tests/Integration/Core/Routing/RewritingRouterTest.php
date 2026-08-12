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

namespace Thelia\Tests\Integration\Core\Routing;

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Routing\RewritingRouter;
use Thelia\Model\Category;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

final class RewritingRouterTest extends IntegrationTestCase
{
    private RewritingRouter $router;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('rewriting_enable', '1');

        $this->router = $this->getService('router.rewrite');
    }

    protected function tearDown(): void
    {
        // ConfigQuery memoizes values in a static cache that would outlive the
        // transaction rollback and leak into the next test.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    private function createCategory(string $title): Category
    {
        $category = $this->createFixtureFactory()->category();
        $category->setLocale('en_US')->setTitle($title)->save();

        return $category;
    }

    private function request(string $url, array $query = []): Request
    {
        $request = Request::create('/'.$url, 'GET', $query);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    public function testMatchRequestResolvesALiveUrl(): void
    {
        $category = $this->createCategory('Live category');
        $url = $category->getRewrittenUrl('en_US');

        $parameters = $this->router->matchRequest($this->request($url));

        self::assertTrue($parameters['_rewritten']);
    }

    public function testMatchRequestRejectsTheUrlOfADeletedObject(): void
    {
        $category = $this->createCategory('Deleted category');
        $url = $category->getRewrittenUrl('en_US');
        $category->delete();

        // Deleting the object does not delete its urls: they are kept under the
        // obsolete view. Nothing is left to serve, and nothing to redirect to.
        $this->expectException(ResourceNotFoundException::class);
        $this->router->matchRequest($this->request($url));
    }

    public function testMatchRequestRejectsTheUrlOfADeletedObjectAskedInAnotherLanguage(): void
    {
        $category = $this->createCategory('Deleted category asked in french');
        $url = $category->getRewrittenUrl('en_US');
        $category->delete();

        // Every deleted object shares the same obsolete view name, so looking up
        // "the same page in another language" used to answer with the url of an
        // unrelated object that happened to have the same id.
        $this->expectException(ResourceNotFoundException::class);
        $this->router->matchRequest($this->request($url, ['lang' => 'fr_FR']));
    }

    public function testMatchRequestRedirectsAReplacedUrlToTheCurrentOne(): void
    {
        $category = $this->createCategory('Replaced category');
        $oldUrl = $category->getRewrittenUrl('en_US');

        $category->setRewrittenUrl('en_US', 'replaced-category-new-address.html');
        $newUrl = $category->getRewrittenUrl('en_US');

        self::assertNotSame($oldUrl, $newUrl);

        try {
            $this->router->matchRequest($this->request($oldUrl));
            self::fail('A replaced url must redirect to the current one.');
        } catch (RedirectException $redirectException) {
            self::assertSame(301, $redirectException->getStatusCode());
            self::assertStringEndsWith('/'.$newUrl, $redirectException->getUrl());
        }
    }
}
