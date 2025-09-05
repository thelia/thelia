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

namespace Thelia\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Thelia\Domain\Module\Payment\PaymentModuleService;

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
