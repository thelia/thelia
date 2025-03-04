<?php

namespace Thelia\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Thelia\Service\Model\PaymentModuleService;

readonly class PaymentModuleProvider implements ProviderInterface
{
    public function __construct(private PaymentModuleService $paymentModuleService)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $moduleId = $context['filters']['moduleId'] ?? null;
        return $this->paymentModuleService->getPaymentModules($moduleId);
    }
}
