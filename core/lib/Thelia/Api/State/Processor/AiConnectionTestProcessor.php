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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\AiConnection as AiConnectionResource;
use Thelia\Domain\Ai\Service\AiConnectionTester;
use Thelia\Model\AiConnectionQuery;

/**
 * Runs the connection test and answers the connection with its new `checkedAt` and `checkError`. A failed
 * test is a 200: the test ran, and `checkError` tells what the provider answered.
 */
final readonly class AiConnectionTestProcessor implements ProcessorInterface
{
    public function __construct(
        private AiConnectionTester $tester,
        private ApiResourcePropelTransformerService $transformer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $connection = AiConnectionQuery::create()->findPk((int) ($uriVariables['id'] ?? 0))
            ?? throw new NotFoundHttpException('AI connection not found.');

        $this->tester->test($connection);

        return $this->transformer->modelToResource(
            resourceClass: AiConnectionResource::class,
            propelModel: $connection,
            context: $context,
        );
    }
}
