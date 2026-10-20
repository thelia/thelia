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
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\FeatureProduct;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Catalog\Product\DTO\ProductFeatureDTO;
use Thelia\Domain\Catalog\Product\ProductFacade;
use Thelia\Model\Admin;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureProduct as FeatureProductModel;
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\FeatureProductTableMap;

/**
 * Writes one feature value of a product through the events the back office
 * dispatches, rather than persisting the row.
 *
 * A free text is not stored on the row: the update action keeps it as a
 * translated value of its own, one translation per language, and a Propel
 * persist would write a row with no text behind it. Going through the events
 * also lets the listeners that follow a product's features (cache
 * invalidation, catalog price rules) hear about the change.
 */
final readonly class FeatureProductProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductFacade $productFacade,
        private ApiResourcePropelTransformerService $transformer,
        private Security $security,
        private SecurityContext $securityContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof FeatureProduct) {
            return $data;
        }

        if ($operation instanceof DeleteOperationInterface) {
            $this->remove($this->model($data));

            return null;
        }

        if ($operation instanceof Post) {
            return $this->create($data, $operation);
        }

        return $this->update($data, $operation, $context);
    }

    private function create(FeatureProduct $data, Operation $operation): FeatureProduct
    {
        if (!isset($data->product, $data->feature)) {
            throw new UnprocessableEntityHttpException('A feature value needs a product and a feature.');
        }

        $productId = (int) $data->getProduct()->getId();
        $featureId = (int) $data->getFeature()->getId();
        $locale = $this->locale($data->getLocale());

        if (true === $data->getIsFreeText()) {
            $text = $this->text($data);

            if ($this->hasValue($productId, $featureId, false)) {
                throw new UnprocessableEntityHttpException('This feature already holds listed values on this product: delete them before writing a free text.');
            }

            // A second POST rewrites the free text in place. That is an update,
            // so it needs the right to update products on top of the right to
            // create that let the request in.
            if ($this->hasValue($productId, $featureId, true) && !$this->mayUpdateProducts()) {
                throw new AccessDeniedHttpException('This feature already holds a free text on this product: rewriting it needs the right to update products.');
            }

            $value = new ProductFeatureDTO($featureId, $text, true, $locale);
        } else {
            $featureAvId = $this->listedValue($data, $featureId);

            if ($this->hasValue($productId, $featureId, true)) {
                throw new UnprocessableEntityHttpException('This feature already holds a free text on this product: delete it before adding a listed value.');
            }

            $value = new ProductFeatureDTO($featureId, $featureAvId, false, $locale);
        }

        return $this->asResource($this->productFacade->setFeatureValue($productId, $value), $locale, $operation);
    }

    private function mayUpdateProducts(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof Admin
            && $this->securityContext->isUserGranted(['ADMIN'], [AdminResources::PRODUCT], [], [AccessManager::UPDATE], $user);
    }

    /**
     * Only a free text is edited in place. A listed value is a link to a value
     * the feature shares with other products: it is changed by deleting it and
     * posting the new one.
     */
    private function update(FeatureProduct $data, Operation $operation, array $context): FeatureProduct
    {
        $model = $this->model($data);

        if (!$model->getIsFreeText()) {
            throw new UnprocessableEntityHttpException('A listed value is not edited in place: delete it and post the new one.');
        }

        if (!$this->sends('free_text_value', $context)) {
            throw new UnprocessableEntityHttpException('The free_text_value field is required.');
        }

        $locale = $this->locale($data->getLocale());

        $written = $this->productFacade->setFeatureValue(
            (int) $model->getProductId(),
            new ProductFeatureDTO((int) $model->getFeatureId(), $this->text($data), true, $locale),
        );

        return $this->asResource($written, $locale, $operation);
    }

    /**
     * The delete event removes every value the feature holds on the product, the
     * way the back office clears a feature before writing it again; the database
     * removes the value a free text owned along with its row. A listed value that
     * had siblings writes them back.
     */
    private function remove(FeatureProductModel $model): void
    {
        $productId = (int) $model->getProductId();
        $featureId = (int) $model->getFeatureId();
        $isFreeText = (bool) $model->getIsFreeText();

        /** @var list<int> $siblingValueIds */
        $siblingValueIds = $isFreeText ? [] : array_map(
            'intval',
            FeatureProductQuery::create()
                ->filterByProductId($productId)
                ->filterByFeatureId($featureId)
                ->filterById((int) $model->getId(), Criteria::NOT_EQUAL)
                ->orderByPosition()
                ->select([FeatureProductTableMap::COL_FEATURE_AV_ID])
                ->find()
                ->getData(),
        );

        $this->productFacade->deleteFeatureValue($productId, $featureId);

        foreach ($siblingValueIds as $siblingValueId) {
            $this->productFacade->setFeatureValue($productId, new ProductFeatureDTO($featureId, $siblingValueId));
        }
    }

    private function text(FeatureProduct $data): string
    {
        $text = isset($data->free_text_value) ? $data->getFreeTextValue() : '';

        if ('' === trim($text)) {
            throw new UnprocessableEntityHttpException('A free text value cannot be empty: delete the value instead.');
        }

        return $text;
    }

    private function listedValue(FeatureProduct $data, int $featureId): int
    {
        if (!isset($data->featureAv)) {
            throw new UnprocessableEntityHttpException('A value that is not a free text needs a featureAv.');
        }

        $featureAv = FeatureAvQuery::create()->findPk((int) $data->getFeatureAv()->getId());

        if (null === $featureAv || (int) $featureAv->getFeatureId() !== $featureId) {
            throw new UnprocessableEntityHttpException('This featureAv is not a value of this feature.');
        }

        // A free text is stored as a value of its own, private to the product
        // that wrote it: linking another product to it would let the owner's
        // edits and deletion reach that product too.
        $ownedByAFreeText = FeatureProductQuery::create()
            ->filterByFeatureAvId((int) $featureAv->getId())
            ->filterByIsFreeText(true)
            ->exists();

        if ($ownedByAFreeText) {
            throw new UnprocessableEntityHttpException('This featureAv is the free text of a product, not a value the feature lists.');
        }

        return (int) $featureAv->getId();
    }

    private function hasValue(int $productId, int $featureId, bool $freeText): bool
    {
        return FeatureProductQuery::create()
            ->filterByProductId($productId)
            ->filterByFeatureId($featureId)
            ->filterByIsFreeText($freeText)
            ->exists();
    }

    /**
     * The language the payload names, the shop's default language when it names none.
     */
    private function locale(?string $locale): string
    {
        if (null === $locale || '' === $locale) {
            return (string) Lang::getDefaultLanguage()->getLocale();
        }

        if (null === LangQuery::create()->findOneByLocale($locale)) {
            throw new UnprocessableEntityHttpException(\sprintf('The locale "%s" is not a language of this shop.', $locale));
        }

        return $locale;
    }

    /**
     * Whether the request body carries the field: a PATCH starts from the stored
     * row, so a field left out of the body is still set on the resource.
     */
    private function sends(string $field, array $context): bool
    {
        $request = $context['request'] ?? null;

        if (!$request instanceof Request) {
            return true;
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return \is_array($payload) && \array_key_exists($field, $payload);
    }

    private function model(FeatureProduct $data): FeatureProductModel
    {
        $model = $data->getPropelModel();

        if (!$model instanceof FeatureProductModel) {
            $model = FeatureProductQuery::create()->findPk($data->getId());
        }

        if (!$model instanceof FeatureProductModel) {
            throw new UnprocessableEntityHttpException('This feature value does not exist any more.');
        }

        return $model;
    }

    /**
     * Read back from what was stored, the text in the language it was written in.
     */
    private function asResource(?FeatureProductModel $model, string $locale, Operation $operation): FeatureProduct
    {
        if (null === $model) {
            throw new UnprocessableEntityHttpException('The feature value could not be written.');
        }

        $context = $operation->getNormalizationContext() ?? [];
        $context['filters']['locale'] = $locale;

        /** @var FeatureProduct $resource */
        $resource = $this->transformer->modelToResource(
            resourceClass: FeatureProduct::class,
            propelModel: $model,
            context: $context,
        );

        return $resource;
    }
}
