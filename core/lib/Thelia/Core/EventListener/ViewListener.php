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

namespace Thelia\Core\EventListener;

use Symfony\Cmf\Component\Routing\ChainRouterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Router;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Exception\OrderException;

/**
 *
 * ViewSubscriber Main class subscribing to view http response.
 *
 * @TODO Look if it's possible to block this definition in dependency-injection
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */

class ViewListener implements EventSubscriberInterface
{
    /** @var ParserInterface */
    protected $parser;

    /** @var TemplateHelperInterface */
    protected $templateHelper;

    /** @var Request */
    protected $request;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var ChainRouterInterface */
    protected $chainRouter;

    /**
     * ViewListener constructor.
     */
    public function __construct(
        ParserInterface $parser,
        TemplateHelperInterface $templateHelper,
        RequestStack $requestStack,
        EventDispatcherInterface $eventDispatcher,
        ChainRouterInterface $chainRouter
    ) {
        $this->parser = $parser;
        $this->templateHelper = $templateHelper;
        $this->request = $requestStack->getCurrentRequest();
        $this->eventDispatcher = $eventDispatcher;
        $this->chainRouter = $chainRouter;
    }

    /**
     * Launch the parser defined on the constructor and get the result.
     *
     * The result is transform id needed into a Response object
     *
     */
    public function onKernelView(ViewEvent $event)
    {
        $this->parser->setTemplateDefinition($this->templateHelper->getActiveFrontTemplate(), true);
        $response = null;
        try {
            $view = $this->request->attributes->get('_view');

            $viewId = $this->request->attributes->get($view . '_id');

            $this->eventDispatcher->dispatch(new ViewCheckEvent($view, $viewId), TheliaEvents::VIEW_CHECK);

            $content = $this->parser->render($view . '.html');

            if ($content instanceof Response) {
                $response = $content;
            } else {
                $response = new Response($content, $this->parser->getStatus() ?: 200);
            }
        } catch (ResourceNotFoundException $e) {
            throw new NotFoundHttpException();
        } catch (OrderException $e) {
            switch ($e->getCode()) {
                case OrderException::CART_EMPTY:
                    // Redirect to the cart template
                    $response = new RedirectResponse($this->chainRouter->generate($e->cartRoute, $e->arguments, Router::ABSOLUTE_URL));
                    break;
                case OrderException::UNDEFINED_DELIVERY:
                    // Redirect to the delivery choice template
                    $response = new RedirectResponse($this->chainRouter->generate($e->orderDeliveryRoute, $e->arguments, Router::ABSOLUTE_URL));
                    break;
            }
            if (null === $response) {
                throw $e;
            }
        }

        $event->setResponse($response);
    }

    public function beforeKernelView(ViewEvent $event)
    {
        $request = $this->request;

        if (null === $view = $request->attributes->get('_view')) {
            $request->attributes->set('_view', $this->findView($request));
        }

        if (null === $request->attributes->get($view . '_id')) {
            $request->attributes->set($view . '_id', $this->findViewId($request, $view));
        }
    }

    public function findView(Request $request)
    {
        if (! $view = $request->query->get('view')) {
            $view = 'index';
            if ($request->request->has('view')) {
                $view = $request->request->get('view');
            }
        }

        return $view;
    }

    public function findViewId(Request $request, $view)
    {
        if (! $viewId = $request->query->get($view . '_id')) {
            $viewId = 0;
            if ($request->request->has($view . '_id')) {
                $viewId = $request->request->get($view . '_id');
            }
        }

        return $viewId;
    }

    /**
     * {@inheritdoc}
     * api
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW =>[
                ['onKernelView', 0],
                ['beforeKernelView', 5]
            ]
        ];
    }
}
