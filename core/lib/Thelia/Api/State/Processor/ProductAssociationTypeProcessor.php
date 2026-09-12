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
use Symfony\Component\HttpFoundation\Request;
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
 * A wording is written field by field, as the payload carried it. The resource
 * cannot tell a description it was not given from one set to null, and a patch
 * correcting a heading says nothing about the paragraph under it: the request body
 * is what says which fields were sent.
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

        return null === $data->getId() ? $this->create($data, $context) : $this->update($data, $context);
    }

    private function create(ProductAssociationType $data, array $context): ProductAssociationType
    {
        $wordings = $this->wordings($data, $context);
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
        $this->writeWordings($data, $wordings);

        return $data;
    }

    /**
     * A payload with no wording — a patch toggling the visibility, typically — writes
     * the flags alone: the wording of every language stays where it was.
     */
    private function update(ProductAssociationType $data, array $context): ProductAssociationType
    {
        $wordings = $this->wordings($data, $context);

        if ([] === $wordings) {
            $this->dispatch($this->flagsUpdateEvent($data), TheliaEvents::PRODUCT_ASSOCIATION_TYPE_UPDATE);

            return $data;
        }

        $this->writeWordings($data, $wordings);

        return $data;
    }

    /**
     * @param array<string, array{title?: string, description?: string|null}> $wordings
     */
    private function writeWordings(ProductAssociationType $data, array $wordings): void
    {
        foreach ($wordings as $locale => $wording) {
            $event = $this->flagsUpdateEvent($data);
            $event->setLocale($locale);

            if (\array_key_exists('title', $wording)) {
                $event->setTitle($wording['title']);
            }

            if (\array_key_exists('description', $wording)) {
                $event->setDescription($wording['description']);
            }

            $this->dispatch($event, TheliaEvents::PRODUCT_ASSOCIATION_TYPE_UPDATE);
        }
    }

    private function flagsUpdateEvent(ProductAssociationType $data): ProductAssociationTypeUpdateEvent
    {
        $event = new ProductAssociationTypeUpdateEvent((int) $data->getId());
        $event
            ->setVisible((int) $data->isVisible())
            ->setReciprocal((int) $data->isReciprocal());

        return $event;
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
     * The wording of each language the payload carries, with the fields it carries
     * and no other.
     *
     * @return array<string, array{title?: string, description?: string|null}>
     */
    private function wordings(ProductAssociationType $data, array $context): array
    {
        $sentI18ns = $this->sentI18ns($context);
        $wordings = [];

        foreach ($data->getI18ns() as $locale => $i18n) {
            if (!$i18n instanceof ProductAssociationTypeI18n) {
                continue;
            }

            $locale = (string) $locale;
            $sent = $sentI18ns[$locale] ?? null;
            $wording = [];

            if (!\is_array($sent) || \array_key_exists('title', $sent)) {
                $wording['title'] = (string) $i18n->getTitle();
            }

            if (!\is_array($sent) || \array_key_exists('description', $sent)) {
                $wording['description'] = $i18n->getDescription();
            }

            $wordings[$locale] = $wording;
        }

        return $wordings;
    }

    /**
     * The `i18ns` object of the request body, or null when there is no readable body:
     * the resource is then taken as the payload, every field of it included.
     *
     * @return array<string, mixed>|null
     */
    private function sentI18ns(array $context): ?array
    {
        $request = $context['request'] ?? null;

        if (!$request instanceof Request) {
            return null;
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $sentI18ns = \is_array($payload) ? ($payload['i18ns'] ?? null) : null;

        return \is_array($sentI18ns) ? $sentI18ns : null;
    }
}
