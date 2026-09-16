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
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\ProductVideo;
use Thelia\Api\Resource\ProductVideoI18n;
use Thelia\Domain\Media\DTO\ProductVideoCreateDTO;
use Thelia\Domain\Media\DTO\ProductVideoUpdateDTO;
use Thelia\Domain\Media\MediaFacade;
use Thelia\Domain\Media\Video\ResolvedVideo;
use Thelia\Domain\Media\Video\UnsupportedVideoUrlException;
use Thelia\Domain\Media\Video\VideoProviderResolver;
use Thelia\Model\Lang;
use Thelia\Model\ProductVideo as ProductVideoModel;
use Thelia\Model\ProductVideoQuery;

/**
 * Writes a product video through the media facade rather than persisting it.
 *
 * Two reasons, and both are the point of the resource: the address a merchant
 * pastes has to go through VideoProviderResolver before anything is stored — the
 * shop keeps the platform and the identifier, never the address — and deleting a
 * video has to take the stored file with it, which a Propel persist would leave
 * behind in the video library.
 */
final readonly class ProductVideoProcessor implements ProcessorInterface
{
    public function __construct(
        private MediaFacade $mediaFacade,
        private VideoProviderResolver $videoProviderResolver,
        private ApiResourcePropelTransformerService $transformer,
        private TranslatorInterface $translator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ProductVideo) {
            return $data;
        }

        if ($operation instanceof DeleteOperationInterface) {
            $this->mediaFacade->deleteVideo($this->model($data));

            return null;
        }

        return null === $data->getId() ? $this->create($data, $operation) : $this->update($data, $operation);
    }

    private function create(ProductVideo $data, Operation $operation): ProductVideo
    {
        $resolved = $this->resolveUrl($data->getUrl());
        $locale = $this->firstLocale($data);
        $wording = $this->wording($data, $locale);

        $video = $this->mediaFacade->createVideo(new ProductVideoCreateDTO(
            productId: (int) $data->getProduct()->getId(),
            provider: $resolved?->provider,
            externalId: $resolved?->externalId,
            thumbnailImageId: $data->getThumbnailImage()?->getId(),
            locale: $locale,
            title: $wording['title'],
            alt: $wording['alt'],
            description: $wording['description'],
            chapo: $wording['chapo'],
            postscriptum: $wording['postscriptum'],
            visible: $data->isVisible(),
        ));

        $this->writeRemainingLocales($video, $data, $locale);

        return $this->asResource($video, $operation);
    }

    private function update(ProductVideo $data, Operation $operation): ProductVideo
    {
        $video = $this->model($data);
        $resolved = $this->resolveUrl($data->getUrl());
        $locale = $this->firstLocale($data);
        $wording = $this->wording($data, $locale);

        $video = $this->mediaFacade->updateVideo($video, new ProductVideoUpdateDTO(
            locale: $locale,
            provider: $resolved?->provider,
            externalId: $resolved?->externalId,
            thumbnailImageId: $data->getThumbnailImage()?->getId() ?? false,
            title: $wording['title'],
            alt: $wording['alt'],
            description: $wording['description'],
            chapo: $wording['chapo'],
            postscriptum: $wording['postscriptum'],
            visible: $data->isVisible(),
        ));

        $this->writeRemainingLocales($video, $data, $locale);

        if (null !== $data->getPosition() && $data->getPosition() !== $video->getPosition()) {
            $this->mediaFacade->updateVideoPosition($video, $data->getPosition());
            $video->reload();
        }

        return $this->asResource($video, $operation);
    }

    /**
     * Each remaining language is written on its own, the way a merchant switching
     * languages in the back office would.
     */
    private function writeRemainingLocales(ProductVideoModel $video, ProductVideo $data, string $writtenLocale): void
    {
        foreach ($data->getI18ns() as $locale => $i18n) {
            if ((string) $locale === $writtenLocale || !$i18n instanceof ProductVideoI18n) {
                continue;
            }

            $wording = $this->wording($data, (string) $locale);

            $this->mediaFacade->updateVideo($video, new ProductVideoUpdateDTO(
                locale: (string) $locale,
                title: $wording['title'],
                alt: $wording['alt'],
                description: $wording['description'],
                chapo: $wording['chapo'],
                postscriptum: $wording['postscriptum'],
            ));
        }
    }

    private function resolveUrl(?string $url): ?ResolvedVideo
    {
        if (null === $url || '' === trim($url)) {
            return null;
        }

        try {
            return $this->videoProviderResolver->resolve($url);
        } catch (UnsupportedVideoUrlException $exception) {
            throw new UnprocessableEntityHttpException($this->translator->trans('This address is not recognised. Accepted platforms: %platforms%.', ['%platforms%' => $exception->getEnabledProviderLabels()], 'core'), $exception);
        }
    }

    private function model(ProductVideo $data): ProductVideoModel
    {
        $video = ProductVideoQuery::create()->findPk($data->getId());

        if (!$video instanceof ProductVideoModel) {
            throw new UnprocessableEntityHttpException('This video does not exist any more.');
        }

        return $video;
    }

    /**
     * The language the create event carries: the first one the payload words, or
     * the default language when it words none.
     */
    private function firstLocale(ProductVideo $data): string
    {
        foreach ($data->getI18ns() as $locale => $i18n) {
            if ($i18n instanceof ProductVideoI18n) {
                return (string) $locale;
            }
        }

        return (string) Lang::getDefaultLanguage()->getLocale();
    }

    /**
     * @return array{title: ?string, alt: ?string, description: ?string, chapo: ?string, postscriptum: ?string}
     */
    private function wording(ProductVideo $data, string $locale): array
    {
        $i18n = null;

        foreach ($data->getI18ns() as $candidateLocale => $candidate) {
            if ((string) $candidateLocale === $locale) {
                $i18n = $candidate;
                break;
            }
        }

        if (!$i18n instanceof ProductVideoI18n) {
            return ['title' => null, 'alt' => null, 'description' => null, 'chapo' => null, 'postscriptum' => null];
        }

        return [
            'title' => $i18n->getTitle(),
            'alt' => $i18n->getAlt(),
            'description' => $i18n->getDescription(),
            'chapo' => $i18n->getChapo(),
            'postscriptum' => $i18n->getPostscriptum(),
        ];
    }

    /**
     * The response is read back from what was stored, so the address a merchant
     * pasted never travels back: the row holds a platform and an identifier, and
     * there is no column it could have come out of.
     */
    private function asResource(ProductVideoModel $video, Operation $operation): ProductVideo
    {
        /** @var ProductVideo $resource */
        $resource = $this->transformer->modelToResource(
            resourceClass: ProductVideo::class,
            propelModel: $video,
            context: $operation->getNormalizationContext() ?? [],
        );

        return $resource;
    }
}
