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

namespace Thelia\Tests\Unit\Core\View;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\TheliaHttpKernel;
use Thelia\Core\View\ViewRenderer;
use Thelia\Domain\Order\Exception\OrderException;
use Thelia\Tests\Support\Log\SilencedTlog;

/**
 * A front-office view is a page of the shop: it renders for a Thelia request that names a view
 * and does not opt out, and a checkout step reached too early sends the customer back to the
 * page that lets them go on instead of showing an error.
 */
final class ViewRendererTest extends TestCase
{
    use SilencedTlog;

    protected function setUp(): void
    {
        // A request without a view is logged: keep the logger off the database and the disk.
        $this->silenceTlog();
    }

    protected function tearDown(): void
    {
        $this->restoreTlog();
    }

    public function testAViewNamedByTheRequestIsRenderedByTheParser(): void
    {
        $response = $this->renderer($this->parserRendering('<h1>Cart</h1>'))->render($this->request('cart'));

        self::assertSame('<h1>Cart</h1>', $response->getContent());
        self::assertSame(200, $response->getStatusCode());
    }

    public function testTheTemplateIsNamedAfterTheViewAndTheEngineExtension(): void
    {
        $parser = $this->createMock(ParserInterface::class);
        $parser->method('getFileExtension')->willReturn('twig');
        $parser->expects(self::once())->method('render')->with('product.twig')->willReturn('<h1>Chair</h1>');

        $this->renderer($parser)->render($this->request('product'));
    }

    public function testTheViewIsCheckedWithTheIdTheRequestCarries(): void
    {
        $request = $this->request('product');
        $request->attributes->set('product_id', 12);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(
            self::callback(static fn (object $event): bool => $event instanceof ViewCheckEvent && 'product' === $event->getView() && 12 === $event->getViewId()),
            TheliaEvents::VIEW_CHECK,
        );

        $this->renderer($this->parserRendering('<h1>Chair</h1>'), null, $dispatcher)->render($request);
    }

    public function testTheStatusTheParserSetIsTheOneAnswered(): void
    {
        $parser = $this->createStub(ParserInterface::class);
        $parser->method('getFileExtension')->willReturn('html');
        $parser->method('render')->willReturn('<h1>Gone</h1>');
        $parser->method('getStatus')->willReturn(410);

        self::assertSame(410, $this->renderer($parser)->render($this->request('discontinued'))->getStatusCode());
    }

    public function testAViewTheTemplateKeepsInternalIsNotFound(): void
    {
        $templateDefinition = $this->createStub(TemplateDefinition::class);
        $templateDefinition->method('getInternalViews')->willReturn(['checkout-payment']);

        $parser = $this->createMock(ParserInterface::class);
        $parser->method('getTemplateDefinition')->willReturn($templateDefinition);
        $parser->expects(self::never())->method('render');

        $this->expectException(NotFoundHttpException::class);

        $this->renderer($parser)->render($this->request('checkout-payment'));
    }

    public function testAViewTheTemplateDoesNotKeepInternalIsRendered(): void
    {
        $templateDefinition = $this->createStub(TemplateDefinition::class);
        $templateDefinition->method('getInternalViews')->willReturn(['checkout-payment']);

        $parser = $this->createStub(ParserInterface::class);
        $parser->method('getTemplateDefinition')->willReturn($templateDefinition);
        $parser->method('getFileExtension')->willReturn('html');
        $parser->method('render')->willReturn('<h1>Cart</h1>');

        self::assertSame('<h1>Cart</h1>', $this->renderer($parser)->render($this->request('cart'))->getContent());
    }

    public function testARequestThatIgnoresTheTheliaViewIsNotFound(): void
    {
        $request = $this->request('cart');
        $request->attributes->set(TheliaHttpKernel::IGNORE_THELIA_VIEW, true);

        $this->expectException(NotFoundHttpException::class);

        $this->renderer($this->parserRendering('<h1>Cart</h1>'))->render($request);
    }

    public function testARequestThatDoesNotComeFromTheTheliaKernelIsNotFound(): void
    {
        $request = SymfonyRequest::create('/cart');
        $request->attributes->set('_view', 'cart');

        $this->expectException(NotFoundHttpException::class);

        $this->renderer($this->parserRendering('<h1>Cart</h1>'))->render($request);
    }

    public function testARequestWithoutAViewIsNotFound(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_view', null);

        $this->expectException(NotFoundHttpException::class);

        $this->renderer($this->parserRendering('<h1>Home</h1>'))->render($request);
    }

    public function testAnEmptyCartSendsTheCustomerBackToTheCart(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('generate')
            ->with('cart.view', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://shop.example/cart');

        $response = $this->renderer($this->parserThrowing(new OrderException('Cart is empty', OrderException::CART_EMPTY)), $router)->render($this->request('order-delivery'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://shop.example/cart', $response->getTargetUrl());
    }

    public function testAMissingDeliveryChoiceSendsTheCustomerBackToTheDeliveryStep(): void
    {
        $exception = new OrderException('No delivery chosen', OrderException::UNDEFINED_DELIVERY, ['step' => 2]);
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('generate')
            ->with('order.delivery', ['step' => 2], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://shop.example/order/delivery');

        $response = $this->renderer($this->parserThrowing($exception), $router)->render($this->request('order-payment'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://shop.example/order/delivery', $response->getTargetUrl());
    }

    public function testAnOrderErrorWithNoPageToGoBackToIsNotHidden(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::never())->method('generate');

        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('Unexpected order failure');

        $this->renderer($this->parserThrowing(new OrderException('Unexpected order failure', OrderException::UNKNOWN_EXCEPTION)), $router)->render($this->request('order-payment'));
    }

    private function renderer(ParserInterface $parser, ?RouterInterface $router = null, ?EventDispatcherInterface $dispatcher = null): ViewRenderer
    {
        $parserResolver = $this->createStub(ParserResolver::class);
        $parserResolver->method('getParserByCurrentRequest')->willReturn($parser);

        return new ViewRenderer(
            $parserResolver,
            $dispatcher ?? $this->createStub(EventDispatcherInterface::class),
            $router ?? $this->createStub(RouterInterface::class),
        );
    }

    private function request(string $view): Request
    {
        $request = Request::create('/'.$view);
        $request->attributes->set('_view', $view);

        return $request;
    }

    private function parserRendering(string $content): ParserInterface
    {
        $parser = $this->createStub(ParserInterface::class);
        $parser->method('getFileExtension')->willReturn('html');
        $parser->method('render')->willReturn($content);

        return $parser;
    }

    private function parserThrowing(\Throwable $exception): ParserInterface
    {
        $parser = $this->createStub(ParserInterface::class);
        $parser->method('getFileExtension')->willReturn('html');
        $parser->method('render')->willThrowException($exception);

        return $parser;
    }
}
