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

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Resource\ProductAssociationType;
use Thelia\Api\Resource\ProductAssociationTypeI18n;
use Thelia\Core\Event\ProductAssociationType\ProductAssociationTypeCreateEvent;
use Thelia\Core\Event\ProductAssociationType\ProductAssociationTypeDeleteEvent;
use Thelia\Core\Event\ProductAssociationType\ProductAssociationTypeUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Lang;
use Thelia\Model\ProductAssociationType as ProductAssociationTypeModel;

/**
 * Writes a relation type through the events the back-office screen uses, instead
 * of persisting it.
 *
 * The core holds three guards on these types, and they are the reason this
 * processor exists: the accessory type may not be deleted because the core names
 * it by its code, a type still carrying relations may not be deleted either, and
 * a code never changes once set. A Propel persist here would let the API delete
 * the accessory type or rename a code the front-office blocks are keyed on.
 *
 * The events carry one locale each, as the back-office form does. A payload
 * holding several translations is therefore written as a create followed by an
 * update per remaining locale — the same path a merchant switching languages in
 * the back office would take.
 *
 * The action reports a refusal by \LogicException, which is its documented
 * channel; it becomes a 422 carrying the translated message.
 */
final readonly class ProductAssociationTypeProcessor implements ProcessorInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ProductAssociationType) {
            return $data;
        }

        if ($operation instanceof DeleteOperationInterface) {
            $this->delete($data);

            return null;
        }

        return null === $data->getId() ? $this->create($data) : $this->update($data);
    }

    private function create(ProductAssociationType $data): ProductAssociationType
    {
        $wordings = $this->wordings($data);
        $firstLocale = array_key_first($wordings) ?? (string) Lang::getDefaultLanguage()->getLocale();

        $event = new ProductAssociationTypeCreateEvent();
        $event
            ->setLocale($firstLocale)
            ->setCode($data->getCode())
            ->setTitle($wordings[$firstLocale]['title'] ?? '')
            ->setDescription($wordings[$firstLocale]['description'] ?? null)
            ->setVisible((int) $data->isVisible())
            ->setReciprocal((int) $data->isReciprocal());

        $this->dispatch($event, TheliaEvents::PRODUCT_ASSOCIATION_TYPE_CREATE);

        $created = $event->getProductAssociationType();

        if (!$created instanceof ProductAssociationTypeModel) {
            throw new UnprocessableEntityHttpException('The product relation type could not be created.');
        }

        $data->setId($created->getId());
        $data->setPosition($created->getPosition());

        unset($wordings[$firstLocale]);
        $this->writeRemainingWordings($data, $wordings);

        return $data;
    }

    private function update(ProductAssociationType $data): ProductAssociationType
    {
        $wordings = $this->wordings($data);

        if ([] === $wordings) {
            $this->dispatchUpdate($data, (string) Lang::getDefaultLanguage()->getLocale(), null, null);

            return $data;
        }

        foreach ($wordings as $locale => $wording) {
            $this->dispatchUpdate($data, $locale, $wording['title'], $wording['description']);
        }

        return $data;
    }

    private function writeRemainingWordings(ProductAssociationType $data, array $wordings): void
    {
        foreach ($wordings as $locale => $wording) {
            $this->dispatchUpdate($data, $locale, $wording['title'], $wording['description']);
        }
    }

    private function dispatchUpdate(ProductAssociationType $data, string $locale, ?string $title, ?string $description): void
    {
        $event = new ProductAssociationTypeUpdateEvent((int) $data->getId());
        $event
            ->setLocale($locale)
            ->setTitle($title ?? '')
            ->setDescription($description)
            ->setVisible((int) $data->isVisible())
            ->setReciprocal((int) $data->isReciprocal());

        $this->dispatch($event, TheliaEvents::PRODUCT_ASSOCIATION_TYPE_UPDATE);
    }

    private function delete(ProductAssociationType $data): void
    {
        $this->dispatch(
            new ProductAssociationTypeDeleteEvent((int) $data->getId()),
            TheliaEvents::PRODUCT_ASSOCIATION_TYPE_DELETE,
        );
    }

    private function dispatch(object $event, string $eventName): void
    {
        try {
            $this->eventDispatcher->dispatch($event, $eventName);
        } catch (\LogicException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }

    /**
     * @return array<string, array{title: string, description: string|null}>
     */
    private function wordings(ProductAssociationType $data): array
    {
        $wordings = [];

        foreach ($data->getI18ns() as $locale => $i18n) {
            if (!$i18n instanceof ProductAssociationTypeI18n) {
                continue;
            }

            $wordings[(string) $locale] = [
                'title' => (string) $i18n->getTitle(),
                'description' => $i18n->getDescription(),
            ];
        }

        return $wordings;
    }
}
