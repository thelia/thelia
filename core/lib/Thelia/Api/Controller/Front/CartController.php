<?php

namespace Thelia\Api\Controller\Front;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Cart;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\CartQuery;

#[AsController]
readonly class CartController
{
    public function __invoke(
        Security                            $security,
        ApiResourcePropelTransformerService $apiResourcePropelTransformerService,
        RequestStack                        $requestStack,
        EventDispatcherInterface            $eventDispatcher,
        Session                             $session,
    ): PropelResourceInterface
    {
        $request = $requestStack->getCurrentRequest();
        $cart = $session->getSessionCart($eventDispatcher);
        if (null === $cart) {
            throw new NotFoundHttpException('Cart not found.');
        }
        $cart = CartQuery::create()->findOneById($cart->getId());
        if (null === $cart) {
            throw new NotFoundHttpException('Cart not found.');
        }
        $operation = $request->get('_api_operation');

        return $apiResourcePropelTransformerService->modelToResource(Cart::class,$cart,$operation->getNormalizationContext());
    }
}
