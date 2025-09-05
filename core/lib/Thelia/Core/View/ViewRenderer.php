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

namespace Thelia\Core\View;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as SfRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\TheliaHttpKernel;
use Thelia\Domain\Order\Exception\OrderException;
use Thelia\Log\Tlog;

readonly class ViewRenderer
{
    public function __construct(
        private ParserResolver $parserResolver,
        private EventDispatcherInterface $eventDispatcher,
        private RouterInterface $router,
    ) {
    }

    public function render(SfRequest $request): Response
    {
        if ($request->attributes->get(TheliaHttpKernel::IGNORE_THELIA_VIEW, false)) {
            throw new NotFoundHttpException();
        }

        if (!$request instanceof TheliaRequest) {
            throw new NotFoundHttpException();
        }

        $view = (string) $request->attributes->get('_view', '');
        if ('' === $view) {
            Tlog::getInstance()->error(
                'No view found ',
            );
            throw new NotFoundHttpException();
        }

        try {
            $parser = $this->parserResolver->getParserByCurrentRequest();
            if (null === $parser) {
                throw new NotFoundHttpException();
            }

            $viewId = $request->attributes->get($view.'_id');
            $this->eventDispatcher->dispatch(new ViewCheckEvent($view, $viewId), TheliaEvents::VIEW_CHECK);

            $content = $parser->render($view.'.'.$parser->getFileExtension());

            return $content instanceof Response
                ? $content
                : new Response($content, $parser->getStatus() ?: 200);
        } catch (ResourceNotFoundException) {
            throw new NotFoundHttpException();
        } catch (OrderException $e) {
            $location = match ($e->getCode()) {
                OrderException::CART_EMPTY => $this->router->generate($e->cartRoute, $e->arguments, UrlGeneratorInterface::ABSOLUTE_URL),
                OrderException::UNDEFINED_DELIVERY => $this->router->generate($e->orderDeliveryRoute, $e->arguments, UrlGeneratorInterface::ABSOLUTE_URL),
                default => null,
            };

            if ($location) {
                return new RedirectResponse($location);
            }

            throw $e;
        }
    }
}
