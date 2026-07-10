<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Api\Bridge\Propel\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Api\Resource\RewritingUrl;
use Thelia\Model\RewritingUrlQuery;

/**
 * Persists a RewritingUrl and, when a new active URL is created for an object
 * (same view/viewId/viewLocale), flags the previously active URL(s) as redirected
 * to the new one, mirroring UrlRewritingTrait::setRewrittenUrl().
 */
readonly class RewritingUrlProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $isCreation = !isset($uriVariables['id']);

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($isCreation && $result instanceof RewritingUrl) {
            $this->redirectPreviousUrls($result);
        }

        return $result;
    }

    private function redirectPreviousUrls(RewritingUrl $rewritingUrl): void
    {
        $id = $rewritingUrl->getId();
        $view = $rewritingUrl->view ?? null;
        $viewId = $rewritingUrl->viewId ?? null;
        $viewLocale = $rewritingUrl->viewLocale ?? null;
        $redirected = $rewritingUrl->redirected ?? null;

        // Only a newly created active URL bound to an object triggers the redirection.
        if (null === $id || null === $view || null === $viewId || null === $viewLocale || null !== $redirected) {
            return;
        }

        RewritingUrlQuery::create()
            ->filterByView($view)
            ->filterByViewId($viewId)
            ->filterByViewLocale($viewLocale)
            ->filterById($id, Criteria::NOT_EQUAL)
            ->filterByRedirected(null, Criteria::ISNULL)
            ->update(['Redirected' => $id]);
    }
}
