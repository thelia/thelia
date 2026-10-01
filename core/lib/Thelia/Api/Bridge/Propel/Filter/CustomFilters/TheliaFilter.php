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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Filter\AbstractFilter;

class TheliaFilter extends AbstractFilter
{
    /**
     * The queries the selection was applied to. `AbstractFilter::apply()` calls filterProperty() for
     * every entry of the request, while the selection is the same whichever the entry: it is applied
     * the first time and not again to the same query.
     *
     * @var \WeakMap<ModelCriteria, true>
     */
    private \WeakMap $applied;

    public function __construct(private readonly FilterService $filterService, private readonly RequestStack $requestStack)
    {
        parent::__construct();
        $this->applied = new \WeakMap();
    }

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $request = $context['request'] ?? null;

        if (!$request) {
            $request = $this->requestStack->getMainRequest();
        }

        if (!$request || (!isset($context['filters']['tfilters']) && \count($request->query->all('tfilters')) < 1)) {
            return;
        }

        if (isset($this->applied[$query])) {
            return;
        }

        $this->applied[$query] = true;

        $isApiRoute = $request->request->get('isApiRoute', false);

        if ($isApiRoute) {
            $this->filterService->filterTFilterWithRequest(request: $request, query: $query);
        } else {
            $this->filterService->filterTFilterWithContext(context: $context, query: $query);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}
