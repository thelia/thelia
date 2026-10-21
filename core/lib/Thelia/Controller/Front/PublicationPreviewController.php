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

namespace Thelia\Controller\Front;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\View\ViewRenderer;
use Thelia\Domain\CMS\Publication\Preview\PublicationPreview;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\LangQuery;

/**
 * `GET /preview/{type}/{id}`: the page of a content or a folder whatever its state.
 *
 * A signed-in administrator reaches it as it is. Anyone else needs the signed link the
 * back office hands out: a link with no signature answers 404, so the route gives
 * nothing away, and a link altered or past its expiry answers the same 403, so a caller
 * cannot tell one from the other. The signatures are tried under a per-client limit,
 * which an administrator does not spend. A row that does not exist answers 404 in every
 * case, once the caller is allowed to ask.
 *
 * The page is then rendered through the view renderer like the public one, with the
 * request marked as a preview: the visibility check lets the row through, the answer is
 * kept out of the caches and the theme shows its banner. The language the link names
 * travels as the `locale` query parameter, which the kernel listener applies to the
 * session on every front request (`LangService::handleLang()`) before the controller
 * runs; a language the shop does not have, which that listener ignores, answers 404.
 */
#[AsController]
final readonly class PublicationPreviewController
{
    public function __construct(
        private ViewRenderer $viewRenderer,
        private UriSigner $uriSigner,
        private SecurityContext $securityContext,
        #[Autowire(service: 'limiter.publication_preview_per_client')]
        private RateLimiterFactoryInterface $perClientLimiter,
    ) {
    }

    public function __invoke(Request $request, string $type, int $id): Response
    {
        if (!\in_array($type, PublicationPreview::TYPES, true)) {
            throw new NotFoundHttpException();
        }

        if (!$this->securityContext->hasAdminUser()) {
            if (!$request->query->has('_hash')) {
                throw new NotFoundHttpException();
            }

            if (!$this->perClientLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                throw new TooManyRequestsHttpException();
            }

            if (!$this->uriSigner->checkRequest($request)) {
                throw new AccessDeniedHttpException('This preview link is not valid or has expired.');
            }
        }

        $preview = PublicationPreview::of($type, $id, $request->query->getString('locale'));

        if (!$this->exists($preview)) {
            throw new NotFoundHttpException();
        }

        if (null !== $preview->locale && !LangQuery::create()->filterByActive(1)->filterByLocale($preview->locale)->exists()) {
            throw new NotFoundHttpException();
        }

        $preview->markRequest($request);
        $request->attributes->set('_view', $preview->type);
        $request->attributes->set($preview->type.'_id', $preview->id);

        return $this->viewRenderer->render($request);
    }

    private function exists(PublicationPreview $preview): bool
    {
        return PublicationPreview::CONTENT === $preview->type
            ? ContentQuery::create()->filterById($preview->id)->exists()
            : FolderQuery::create()->filterById($preview->id)->exists();
    }
}
