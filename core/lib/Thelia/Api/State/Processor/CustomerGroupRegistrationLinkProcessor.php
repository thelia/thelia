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
use Thelia\Api\Resource\CustomerGroup;
use Thelia\Domain\Customer\Service\CustomerGroupRegistration;
use Thelia\Model\CustomerGroup as CustomerGroupModel;

/**
 * Opens, regenerates or closes the registration link of the group the URL names, as the operation
 * says (`registration_link_action`). The rights and the refusal of an administrator restricted to a
 * sale unit are those of the operation; the default group answers 409.
 */
final readonly class CustomerGroupRegistrationLinkProcessor implements ProcessorInterface
{
    public const ACTION = 'registration_link_action';
    public const OPEN = 'open';
    public const REGENERATE = 'regenerate';
    public const CLOSE = 'close';

    public function __construct(
        private CustomerGroupRegistration $registration,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CustomerGroup
    {
        if (!$data instanceof CustomerGroup || !($group = $data->getPropelModel()) instanceof CustomerGroupModel) {
            throw new NotFoundHttpException('Customer group not found.');
        }

        $action = $operation->getExtraProperties()[self::ACTION] ?? null;

        match ($action) {
            self::OPEN => $this->registration->open($group),
            self::REGENERATE => $this->registration->regenerate($group),
            self::CLOSE => $this->registration->close($group),
            default => throw new \LogicException('The operation does not say what to do with the registration link.'),
        };

        if (self::CLOSE === $action) {
            return null;
        }

        $data->setRegistrationToken($group->getRegistrationToken());
        $data->readLinks();

        return $data;
    }
}
