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

namespace BackOfficeDefaultTwigBundle\Controller;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\CategoryQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductQuery;
use Twig\Environment;

final class SearchController
{
    private const SEARCH_RESOURCES = ['admin.product', 'admin.category', 'admin.customer', 'admin.order'];

    public function __construct(
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/admin/search', name: 'admin.search', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($this->isDenied()) {
            return new Response($this->twig->render('@BackOfficeDefaultTwig/general_error.html.twig'), Response::HTTP_FORBIDDEN);
        }

        $term = trim((string) $request->query->get('search_term', ''));
        $locale = $this->defaultLocale();
        $results = ['term' => $term, 'products' => [], 'categories' => [], 'customers' => [], 'orders' => []];

        if ($term !== '') {
            foreach (ProductQuery::create()->useProductI18nQuery()->filterByTitle('%'.$term.'%', \Propel\Runtime\ActiveQuery\Criteria::LIKE)->endUse()->distinct()->limit(10)->find() as $product) {
                $product->setLocale($locale);
                $results['products'][] = ['id' => (int) $product->getId(), 'title' => (string) $product->getTitle(), 'ref' => (string) $product->getRef()];
            }

            foreach (CategoryQuery::create()->useCategoryI18nQuery()->filterByTitle('%'.$term.'%', \Propel\Runtime\ActiveQuery\Criteria::LIKE)->endUse()->distinct()->limit(10)->find() as $category) {
                $category->setLocale($locale);
                $results['categories'][] = ['id' => (int) $category->getId(), 'title' => (string) $category->getTitle()];
            }

            foreach (CustomerQuery::create()->filterByFirstname('%'.$term.'%', \Propel\Runtime\ActiveQuery\Criteria::LIKE)->_or()->filterByLastname('%'.$term.'%', \Propel\Runtime\ActiveQuery\Criteria::LIKE)->_or()->filterByEmail('%'.$term.'%', \Propel\Runtime\ActiveQuery\Criteria::LIKE)->limit(10)->find() as $customer) {
                $results['customers'][] = [
                    'id' => (int) $customer->getId(),
                    'firstname' => (string) $customer->getFirstname(),
                    'lastname' => (string) $customer->getLastname(),
                    'email' => (string) $customer->getEmail(),
                ];
            }

            foreach (OrderQuery::create()->filterByRef('%'.$term.'%', \Propel\Runtime\ActiveQuery\Criteria::LIKE)->limit(10)->find() as $order) {
                $results['orders'][] = ['id' => (int) $order->getId(), 'ref' => (string) $order->getRef()];
            }
        }

        return new Response($this->twig->render('@BackOfficeDefaultTwig/search/index.html.twig', $results));
    }

    private function isDenied(): bool
    {
        foreach (self::SEARCH_RESOURCES as $resource) {
            if ($this->access->check($resource, [], AccessManager::VIEW) === null) {
                return false;
            }
        }

        return true;
    }

    private function defaultLocale(): string
    {
        return LangQuery::create()->findOneByByDefault(true)?->getLocale() ?? 'en_US';
    }
}
