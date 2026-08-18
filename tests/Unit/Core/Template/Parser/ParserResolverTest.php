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

namespace Thelia\Tests\Unit\Core\Template\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\Parser\ParserFallback;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Core\View\ViewRenderer;

/**
 * A view name no registered parser can render is a page the shop does not have.
 *
 * The front-office catch-all route reads the last segment of any URL as a view name, so a
 * service worker probe, a mistyped page or a URL left over from a previous site all reach
 * the view chain naming a view no theme ships. The outcome has to be a 404: a 500 hides
 * real failures in the noise and writes a CRITICAL entry per probe in the production log.
 *
 * These tests build the resolver with parsers that render nothing, which is the situation
 * of a shop whose theme is served by a single engine — the Twig parser and a theme that
 * does not ship the template. A shop that also runs the Smarty parser never gets here:
 * that parser claims every template name and reports the missing file from render()
 * instead, which the view chain already turns into a 404.
 */
final class ParserResolverTest extends TestCase
{
    public static function unknownViewProvider(): iterable
    {
        yield 'service worker probed by browsers' => ['sw.js'];
        yield 'service worker, the other conventional name' => ['service-worker.js'];
        yield 'view name no theme declares' => ['brand'];
        yield 'another view name no theme declares' => ['currency'];
        yield 'page of a site the shop replaced' => ['mentions-legales.html'];
    }

    #[DataProvider('unknownViewProvider')]
    public function testAViewNoParserCanRenderIsReportedAsAMissingResource(string $view): void
    {
        $resolver = new ParserResolver([new ParserFallback()], [], new RequestStack(), $this->templateHelper());

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage(\sprintf('Parser for template %s not found', $view));

        $resolver->getParser('/themes/flexy', $view);
    }

    /**
     * The whole point of reporting it that way: the front-office view chain already turns a
     * missing template resource into a 404, so it needs no case of its own for this one.
     */
    #[DataProvider('unknownViewProvider')]
    public function testTheViewChainAnswersNotFoundForAViewNoParserCanRender(string $view): void
    {
        $request = Request::create('/'.$view);
        $request->attributes->set('_view', $view);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $renderer = new ViewRenderer(
            new ParserResolver([new ParserFallback()], [], $requestStack, $this->templateHelper()),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(RouterInterface::class),
        );

        $this->expectException(NotFoundHttpException::class);

        $renderer->render($request);
    }

    private function templateHelper(): TemplateHelperInterface
    {
        $templateDefinition = $this->createMock(TemplateDefinition::class);
        $templateDefinition->method('getAbsolutePath')->willReturn('/themes/flexy');

        $templateHelper = $this->createMock(TemplateHelperInterface::class);
        $templateHelper->method('isAdmin')->willReturn(false);
        $templateHelper->method('getActiveFrontTemplate')->willReturn($templateDefinition);

        return $templateHelper;
    }
}
