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

namespace Thelia\Tests\Integration\Core\Template;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Test\IntegrationTestCase;

/**
 * The parser a request picked must not be the current parser of the next request
 * served by the same process (a worker, or a test sending several requests).
 */
final class ParserResolverResetTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        (new \ReflectionProperty(ParserResolver::class, 'currentParser'))->setValue(null, null);

        parent::tearDown();
    }

    public function testAMainRequestStartsWithoutTheParserOfThePreviousOne(): void
    {
        $this->pickAParser();

        $this->resolver()->onKernelRequest($this->requestEvent(HttpKernelInterface::MAIN_REQUEST));

        self::assertNull(ParserResolver::getCurrentParser());
    }

    public function testASubRequestKeepsTheParserOfItsMainRequest(): void
    {
        $parser = $this->pickAParser();

        $this->resolver()->onKernelRequest($this->requestEvent(HttpKernelInterface::SUB_REQUEST));

        self::assertSame($parser, ParserResolver::getCurrentParser());
    }

    public function testResettingTheServicesBetweenTwoRequestsForgetsTheParser(): void
    {
        $this->pickAParser();

        $this->getService('services_resetter')->reset();

        self::assertNull(ParserResolver::getCurrentParser());
    }

    public function testTheResetListensToTheStartOfEveryRequest(): void
    {
        $listeners = array_filter(
            $this->getService('event_dispatcher')->getListeners(KernelEvents::REQUEST),
            static fn (mixed $listener): bool => \is_array($listener) && $listener[0] instanceof ParserResolver,
        );

        self::assertCount(1, $listeners);
        self::assertSame('onKernelRequest', array_values($listeners)[0][1]);
    }

    private function pickAParser(): object
    {
        $template = $this->getService(TemplateHelperInterface::class)->getActivePdfTemplate();
        $parser = $this->resolver()->getParser($template->getAbsolutePath(), 'invoice');

        self::assertSame($parser, ParserResolver::getCurrentParser());

        return $parser;
    }

    private function requestEvent(int $requestType): RequestEvent
    {
        return new RequestEvent(static::$kernel, Request::create('http://localhost/'), $requestType);
    }

    private function resolver(): ParserResolver
    {
        return $this->getService(ParserResolver::class);
    }
}
