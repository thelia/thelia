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

namespace Thelia\Tests\Integration\Core\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\EventListener\KernelListener;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\HttpFoundation\Session\SessionFactory;
use Thelia\Core\HttpFoundation\Session\SessionManager;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * A listener answers with a response by putting it on the event: what it returns is
 * discarded. The redirect to the domain of the requested language is the only response
 * this listener produces, and it has to reach the visitor.
 */
final class KernelListenerLanguageRedirectTest extends IntegrationTestCase
{
    private const CURRENT_DOMAIN = 'http://en.example.com';
    private const TARGET_DOMAIN = 'http://fr.example.com';

    private KernelListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listener = new KernelListener(
            self::$kernel,
            Translator::getInstance(),
            $this->getService(EventDispatcherInterface::class),
            $this->getService(LangService::class),
            $this->getService(SessionManager::class),
            $this->getService(SessionFactory::class),
            self::$kernel->getCacheDir(),
            false,
            'test',
        );

        ConfigQuery::write('one_domain_foreach_lang', '1');
        LangQuery::create()
            ->findOneByLocale('fr_FR')
            ->setActive(true)
            ->setUrl(self::TARGET_DOMAIN)
            ->save();
    }

    protected function tearDown(): void
    {
        // Both outlive the transaction rollback: ConfigQuery memoizes what it reads, and
        // the admin flag is a static the listener writes on every request.
        ConfigQuery::resetCache();
        TheliaRequest::$isAdminEnv = false;

        parent::tearDown();
    }

    private function event(string $uri, string $method = 'GET', int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = TheliaRequest::create($uri, $method);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return new RequestEvent(self::$kernel, $request, $type);
    }

    public function testTheRedirectToTheLanguageDomainReachesTheVisitor(): void
    {
        $event = $this->event(self::CURRENT_DOMAIN.'/?lang=fr');

        $this->listener->initializeLanguageAndAdmin($event);

        $response = $event->getResponse();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(301, $response->getStatusCode());
        self::assertSame(self::TARGET_DOMAIN, $response->getTargetUrl());
    }

    public function testAFormPostIsNotAnsweredWithARedirect(): void
    {
        // A browser replays a 301 as a GET without the body: redirecting a post would
        // throw away what the visitor submitted.
        $event = $this->event(self::CURRENT_DOMAIN.'/?lang=fr', 'POST');

        $this->listener->initializeLanguageAndAdmin($event);

        self::assertNull($event->getResponse());
    }

    public function testAnApiRequestIsLeftAlone(): void
    {
        $event = $this->event(self::CURRENT_DOMAIN.'/api/front/products?lang=fr');

        $this->listener->initializeLanguageAndAdmin($event);

        self::assertNull($event->getResponse());
    }

    public function testASubRequestIsLeftAlone(): void
    {
        $event = $this->event(self::CURRENT_DOMAIN.'/?lang=fr', 'GET', HttpKernelInterface::SUB_REQUEST);

        $this->listener->initializeLanguageAndAdmin($event);

        self::assertNull($event->getResponse());
    }

    public function testAnAdminRequestIsLeftAlone(): void
    {
        $event = $this->event(self::CURRENT_DOMAIN.'/admin/categories?lang=fr');

        $this->listener->initializeLanguageAndAdmin($event);

        self::assertNull($event->getResponse());
    }

    public function testASingleDomainShopIsLeftAlone(): void
    {
        ConfigQuery::write('one_domain_foreach_lang', '0');

        $event = $this->event(self::CURRENT_DOMAIN.'/?lang=fr');

        $this->listener->initializeLanguageAndAdmin($event);

        self::assertNull($event->getResponse());
    }
}
