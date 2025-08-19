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

namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Exception\OrderException;

/**
 * ViewSubscriber Main class subscribing to view http response.
 *
 * @TODO Look if it's possible to block this definition in dependency-injection
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ViewListener
{
    public const IGNORE_THELIA_VIEW = 'ignore_thelia_view';

    public function __construct(
        protected ParserResolver $parserResolver,
        protected TemplateHelperInterface $templateHelper,
        protected EventDispatcherInterface $eventDispatcher,
        protected RouterInterface $router,
    ) {
    }

    #[AsEventListener(event: KernelEvents::VIEW, priority: 0)]
    public function onKernelView(ViewEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->has(self::IGNORE_THELIA_VIEW)) {
            return;
        }

        try {
            $view = $request->attributes->get('_view');
            $templatePath = $this->templateHelper->getActiveFrontTemplate()->getAbsolutePath();
            $parser = $this->parserResolver->getParser($templatePath, $view);
            $parser->setTemplateDefinition($this->templateHelper->getActiveFrontTemplate(), true);
            $viewId = $request->attributes->get($view.'_id');
            $this->eventDispatcher->dispatch(new ViewCheckEvent($view, $viewId), TheliaEvents::VIEW_CHECK);
            $content = $parser->render($view.'.'.$parser->getFileExtension());
            $response = $content instanceof Response
                ? $content
                : new Response($content, $parser->getStatus() ?: 200);
        } catch (ResourceNotFoundException) {
            throw new NotFoundHttpException();
        } catch (OrderException $e) {
            $response = match ($e->getCode()) {
                OrderException::CART_EMPTY => new RedirectResponse(
                    $this->router->generate($e->cartRoute, $e->arguments, UrlGeneratorInterface::ABSOLUTE_URL),
                ),
                OrderException::UNDEFINED_DELIVERY => new RedirectResponse(
                    $this->router->generate($e->orderDeliveryRoute, $e->arguments, UrlGeneratorInterface::ABSOLUTE_URL),
                ),
                default => null,
            };

            if (!$response instanceof RedirectResponse) {
                throw $e;
            }
        }

        if ($response) {
            $event->setResponse($response);
        }
    }

    #[AsEventListener(event: KernelEvents::VIEW, priority: 5)]
    public function beforeKernelView(ViewEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->has(self::IGNORE_THELIA_VIEW)) {
            return;
        }
        $view = $request->attributes->get('_view', $this->findView($request));
        $request->attributes->set('_view', $view);

        if (!$request->attributes->has($view.'_id')) {
            $request->attributes->set($view.'_id', $this->findViewId($request, $view));
        }
    }

    public function findView(Request $request): string
    {
        return $request->query->get('view') ?: $request->request->get('view', 'index');
    }

    public function findViewId(Request $request, string $view): ?int
    {
        $paramName = $view.'_id';

        $viewId = $request->query->getInt($paramName) ?: $request->request->getInt($paramName);

        return $viewId ?: null;
    }
}
