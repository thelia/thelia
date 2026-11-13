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

namespace Thelia\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Subscription as SubscriptionResource;
use Thelia\Domain\Payment\CustomerPaymentMethodService;
use Thelia\Domain\Subscription\Enum\ActorType;
use Thelia\Domain\Subscription\Exception\SubscriptionActionRefusedException;
use Thelia\Domain\Subscription\Exception\SubscriptionTransitionRefusedException;
use Thelia\Domain\Subscription\SubscriptionLifecycle;
use Thelia\Model\Customer;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\Subscription as SubscriptionModel;

/**
 * What a customer does to one of their subscriptions: cancel, pause, resume, or charge the
 * installments to another of their stored payment methods.
 *
 * The subscription was read by the provider, scoped to the caller by
 * SubscriptionOwnershipExtension (404 for another customer's), and the action is told by the
 * operation. Every change goes through SubscriptionLifecycle, which records the customer as the
 * author; what the state of the subscription does not allow is a 409 (the exceptions are mapped
 * in the API configuration). The payment method is named by the IRI of one of the customer's
 * methods (`{"paymentMethod": "/api/front/account/payment_methods/12"}`), and a method that
 * is not theirs is a 422, as it is on the checkout.
 */
final readonly class SubscriptionActionProcessor implements ProcessorInterface
{
    public const CANCEL = 'cancel';
    public const PAUSE = 'pause';
    public const RESUME = 'resume';
    public const PAYMENT_METHOD = 'payment_method';

    public function __construct(
        private SubscriptionLifecycle $lifecycle,
        private TokenStorageInterface $tokenStorage,
        private ApiResourcePropelTransformerService $transformer,
        private RequestStack $requestStack,
        private CustomerPaymentMethodService $customerPaymentMethods,
    ) {
    }

    /**
     * @throws SubscriptionActionRefusedException
     * @throws SubscriptionTransitionRefusedException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $customer = $this->tokenStorage->getToken()?->getUser();

        if (!$customer instanceof Customer || $customer->isGuest()) {
            throw new AccessDeniedHttpException('A customer must be authenticated to act on a subscription.');
        }

        $model = $data instanceof SubscriptionResource ? $data->getPropelModel() : null;

        // Never reached for a subscription of another customer (the provider answers 404 first);
        // asked again so that no path can act on one.
        if (!$model instanceof SubscriptionModel || (int) $model->getCustomerId() !== (int) $customer->getId()) {
            throw new NotFoundHttpException('Subscription not found.');
        }

        $actorId = (int) $customer->getId();

        $updated = match ($operation->getExtraProperties()['subscription_action'] ?? null) {
            self::CANCEL => $this->lifecycle->cancel($model, ActorType::Customer, $actorId),
            self::PAUSE => $this->lifecycle->pause($model, ActorType::Customer, $actorId),
            self::RESUME => $this->lifecycle->resume($model, ActorType::Customer, $actorId),
            self::PAYMENT_METHOD => $this->lifecycle->changePaymentMethod($model, $this->methodOf($customer), ActorType::Customer, $actorId),
            default => throw new UnprocessableEntityHttpException('Unknown subscription action.'),
        };

        return $this->transformer->modelToResource(
            resourceClass: SubscriptionResource::class,
            propelModel: $updated,
            context: $context,
        );
    }

    private function methodOf(Customer $customer): CustomerPaymentMethod
    {
        $body = json_decode((string) $this->requestStack->getCurrentRequest()?->getContent(), true);
        $iri = \is_array($body) ? ($body['paymentMethod'] ?? null) : null;

        if (!\is_string($iri) || 1 !== preg_match('#/payment_methods/(\d+)$#', $iri, $matches)) {
            throw new UnprocessableEntityHttpException('A paymentMethod is required: the IRI of one of your payment methods.');
        }

        $method = $this->customerPaymentMethods->findOwned($customer, (int) $matches[1]);

        if (null === $method) {
            throw new UnprocessableEntityHttpException('This payment method is not one of yours.');
        }

        return $method;
    }
}
