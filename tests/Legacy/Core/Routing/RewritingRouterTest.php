<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Core\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RequestContext;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Routing\RewritingLoader;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Tools\URL;

/**
 * Class RewritingRouterTest.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class RewritingRouterTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $url = new URL();
    }

    /**
     * @covers \RewritingRouter::generate
     */
    public function testGenerate(): void
    {
        $rewritingRouter = new RewritingLoader();

        $this->expectException(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);
        $rewritingRouter->generate('foo');
    }

    /**
     * @covers \RewritingRouter::match
     */
    public function testMatch(): void
    {
        $rewritingRouter = new RewritingLoader();

        $this->expectException(\Symfony\Component\Routing\Exception\ResourceNotFoundException::class);
        $rewritingRouter->match('foo');
    }

    /**
     * covers RewritingRouter::matchRequest.
     */
    public function testMatchRequestWithNoRewriting(): void
    {
        ConfigQuery::write('rewriting_enable', 0);
        $request = new Request();

        $rewritingRouter = new RewritingLoader();

        $this->expectException(\Symfony\Component\Routing\Exception\ResourceNotFoundException::class);
        $rewritingRouter->matchRequest($request);
    }

    /**
     * covers RewritingRouter::matchRequest.
     */
    public function testMatchRequestWithNonExistingUrl(): void
    {
        ConfigQuery::write('rewriting_enable', 1);
        $request = Request::create('http://test.com/foo');

        $rewritingRouter = new RewritingLoader();

        $this->expectException(\Symfony\Component\Routing\Exception\ResourceNotFoundException::class);
        $rewritingRouter->matchRequest($request);
    }

    /**
     * covers RewritingRouter::matchRequest.
     */
    public function testMatchRequestWithSameLocale(): void
    {
        ConfigQuery::write('rewriting_enable', 1);
        ConfigQuery::write('one_domain_foreach_lang', 0);

        $defaultLang = LangQuery::create()->findOneByByDefault(1);
        $product = ProductQuery::create()->findOne();
        $product->setLocale($defaultLang->getLocale());

        $rewriting = RewritingUrlQuery::create()
            ->filterByView('product')
            ->filterByViewId($product->getId())
            ->filterByViewLocale($defaultLang->getLocale())
            ->filterByRedirected(null)
            ->findOne();

        $request = Request::create('http://test.com/'.$rewriting->getUrl());
        $session = new Session(new MockArraySessionStorage());
        $session->setLang($defaultLang);
        $request->setSession($session);
        $url = new URL();
        $requestContext = new RequestContext();
        $requestContext->fromRequest($request);
        $url->setRequestContext($requestContext);

        $rewritingRouter = new RewritingLoader();
        $parameters = $rewritingRouter->matchRequest($request);

        $this->assertEquals('Thelia\\Controller\\Front\\DefaultController::noAction', $parameters['_controller']);
        $this->assertEquals('rewrite', $parameters['_route']);
        $this->assertTrue($parameters['_rewritten']);

        $this->assertEquals($product->getId(), $request->query->get('product_id'));
        $this->assertEquals('product', $request->attributes->get('_view'));
    }

    /**
     * covers RewritingRouter::matchRequest.
     */
    public function testMatchRequestWithDifferentLocale(): void
    {
        ConfigQuery::write('rewriting_enable', 1);
        ConfigQuery::write('one_domain_foreach_lang', 0);

        $defaultLang = LangQuery::create()->findOneByLocale('en_US');
        $product = ProductQuery::create()->findOne();
        $product->setLocale($defaultLang->getLocale());

        $rewriting = RewritingUrlQuery::create()
            ->filterByView('product')
            ->filterByViewId($product->getId())
            ->filterByViewLocale('fr_FR')
            ->filterByRedirected(null)
            ->findOne();

        $request = Request::create('http://test.com/'.$rewriting->getUrl());
        $session = new Session(new MockArraySessionStorage());
        $session->setLang($defaultLang);
        $request->setSession($session);
        $url = new URL();
        $requestContext = new RequestContext();
        $requestContext->fromRequest($request);
        $url->setRequestContext($requestContext);

        $rewritingRouter = new RewritingLoader();
        $parameters = $rewritingRouter->matchRequest($request);

        $this->assertEquals('Thelia\\Controller\\Front\\DefaultController::noAction', $parameters['_controller']);
        $this->assertEquals('rewrite', $parameters['_route']);
        $this->assertTrue($parameters['_rewritten']);

        $this->assertEquals($product->getId(), $request->query->get('product_id'));
        $this->assertEquals('product', $request->attributes->get('_view'));
        $this->assertNotEquals($defaultLang, $request->getSession()->getLang());
    }

    /**
     * covers RewritingRouter::matchRequest.
     */
    public function testMatchRequestWithDifferentLocaleAndDomain(): void
    {
        ConfigQuery::write('rewriting_enable', 1);
        ConfigQuery::write('one_domain_foreach_lang', 1);

        $defaultLang = LangQuery::create()->findOneByLocale('en_US');
        $defaultLang->setUrl('http://test_en.com');

        $frenchLang = LangQuery::create()->findOneByLocale('fr_FR');
        $saveUrl = $frenchLang->getUrl();
        $frenchLang->setUrl('http://test.com')->save();

        $product = ProductQuery::create()->findOne();
        $product->setLocale($defaultLang->getLocale());

        $rewriting = RewritingUrlQuery::create()
            ->filterByView('product')
            ->filterByViewId($product->getId())
            ->filterByViewLocale('fr_FR')
            ->filterByRedirected(null)
            ->findOne();

        $request = Request::create('http://test_en.com/'.$rewriting->getUrl());
        $session = new Session(new MockArraySessionStorage());
        $session->setLang($defaultLang);
        $request->setSession($session);
        $url = new URL();
        $requestContext = new RequestContext();
        $requestContext->fromRequest($request);
        $url->setRequestContext($requestContext);

        try {
            $rewritingRouter = new RewritingLoader();
            $parameters = $rewritingRouter->matchRequest($request);
        } catch (RedirectException $e) {
            $this->assertMatchesRegularExpression("/http:\/\/test\.com\/.*/", $e->getUrl());

            return;
        }

        $this->fail('->matchRequest must throw a RedirectException');
    }
}
