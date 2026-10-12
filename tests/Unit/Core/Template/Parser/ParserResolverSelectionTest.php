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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * The resolver hands a template to the first parser that claims it, and the request decides
 * which template, front-office or back-office, the parser works on.
 */
final class ParserResolverSelectionTest extends TestCase
{
    protected function tearDown(): void
    {
        // The resolver keeps the parser it picked in a static property: left there,
        // a mock of this test would answer getCurrentParser() in the tests that follow.
        (new \ReflectionProperty(ParserResolver::class, 'currentParser'))->setValue(null, null);
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function rootNames(): iterable
    {
        yield 'no name' => [null];
        yield 'empty name' => [''];
        yield 'the root path' => ['/'];
    }

    #[DataProvider('rootNames')]
    public function testTheRootOfTheShopIsTheIndexTemplate(?string $templateName): void
    {
        $parser = $this->createMock(ParserInterface::class);
        $parser->expects(self::once())->method('supportTemplateRender')->with('/themes/flexy', 'index')->willReturn(true);

        $resolver = new ParserResolver([$parser], [], new RequestStack(), $this->createStub(TemplateHelperInterface::class));

        self::assertSame($parser, $resolver->getParser('/themes/flexy', $templateName));
    }

    public function testANamedTemplateIsAskedAsNamed(): void
    {
        $parser = $this->createMock(ParserInterface::class);
        $parser->expects(self::once())->method('supportTemplateRender')->with('/themes/flexy', 'cart')->willReturn(true);

        $resolver = new ParserResolver([$parser], [], new RequestStack(), $this->createStub(TemplateHelperInterface::class));

        $resolver->getParser('/themes/flexy', 'cart');
    }

    public function testTheFirstParserThatClaimsTheTemplateIsTheOneReturned(): void
    {
        $declining = $this->createStub(ParserInterface::class);
        $declining->method('supportTemplateRender')->willReturn(false);
        $claiming = $this->createStub(ParserInterface::class);
        $claiming->method('supportTemplateRender')->willReturn(true);
        $notReached = $this->createMock(ParserInterface::class);
        $notReached->expects(self::never())->method('supportTemplateRender');

        $resolver = new ParserResolver([$declining, $claiming, $notReached], [], new RequestStack(), $this->createStub(TemplateHelperInterface::class));

        self::assertSame($claiming, $resolver->getParser('/themes/flexy', 'cart'));
    }

    public function testWithoutARequestThereIsNoParserToPick(): void
    {
        $resolver = new ParserResolver([$this->createStub(ParserInterface::class)], [], new RequestStack(), $this->createStub(TemplateHelperInterface::class));

        self::assertNull($resolver->getParserByCurrentRequest());
        self::assertNull($resolver->getCurrentTemplateDefinition());
    }

    public function testAFrontOfficeRequestIsRenderedWithTheActiveFrontTemplate(): void
    {
        $front = $this->templateDefinition('/themes/flexy');
        $back = $this->templateDefinition('/themes/default-twig');
        $parser = $this->createMock(ParserInterface::class);
        $parser->method('supportTemplateRender')->willReturn(true);
        $parser->expects(self::once())->method('setTemplateDefinition')->with($front, true);

        $resolver = $this->resolver($parser, $this->requestStackWithView('cart'), $this->templateHelper(false, $front, $back));

        self::assertSame($parser, $resolver->getParserByCurrentRequest());
        self::assertSame($front, $resolver->getCurrentTemplateDefinition());
    }

    public function testABackOfficeRequestIsRenderedWithTheActiveAdminTemplate(): void
    {
        $front = $this->templateDefinition('/themes/flexy');
        $back = $this->templateDefinition('/themes/default-twig');
        $parser = $this->createMock(ParserInterface::class);
        $parser->expects(self::once())->method('supportTemplateRender')->with('/themes/default-twig', 'orders')->willReturn(true);
        $parser->expects(self::once())->method('setTemplateDefinition')->with($back, true);

        $resolver = $this->resolver($parser, $this->requestStackWithView('orders'), $this->templateHelper(true, $front, $back));

        self::assertSame($parser, $resolver->getParserByCurrentRequest());
        self::assertSame($back, $resolver->getCurrentTemplateDefinition());
    }

    private function resolver(ParserInterface $parser, RequestStack $requestStack, TemplateHelperInterface $templateHelper): ParserResolver
    {
        return new ParserResolver([$parser], [], $requestStack, $templateHelper);
    }

    private function requestStackWithView(string $view): RequestStack
    {
        $request = Request::create('/'.$view);
        $request->attributes->set('_view', $view);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }

    private function templateHelper(bool $isAdmin, TemplateDefinition $front, TemplateDefinition $back): TemplateHelperInterface
    {
        $templateHelper = $this->createStub(TemplateHelperInterface::class);
        $templateHelper->method('isAdmin')->willReturn($isAdmin);
        $templateHelper->method('getActiveFrontTemplate')->willReturn($front);
        $templateHelper->method('getActiveAdminTemplate')->willReturn($back);

        return $templateHelper;
    }

    private function templateDefinition(string $absolutePath): TemplateDefinition
    {
        $templateDefinition = $this->createStub(TemplateDefinition::class);
        $templateDefinition->method('getAbsolutePath')->willReturn($absolutePath);

        return $templateDefinition;
    }
}
