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
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\ProductSaleElementsProductVideo;
use Thelia\Model\ProductSaleElementsProductVideoQuery;

/**
 * Persists a combination-to-video link, after saying no to one that already exists.
 *
 * The schema holds a unique index on the pair, which is what makes the rule true
 * rather than merely enforced here; without this the second call would reach the
 * database and come back as a 500, which tells an integrator nothing about what
 * it did wrong.
 */
final readonly class ProductSaleElementsProductVideoProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private TranslatorInterface $translator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ProductSaleElementsProductVideo) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $alreadyLinked = ProductSaleElementsProductVideoQuery::create()
            ->filterByProductSaleElementsId($data->getProductSaleElementsId())
            ->filterByProductVideoId($data->getProductVideoId())
            ->exists();

        if ($alreadyLinked) {
            throw new UnprocessableEntityHttpException($this->translator->trans('This video is already attached to this combination.', [], 'core'));
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
