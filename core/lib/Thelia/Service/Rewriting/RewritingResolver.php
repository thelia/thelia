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

namespace Thelia\Service\Rewriting;

use Propel\Runtime\Exception\PropelException;
use Thelia\Exception\UrlRewritingException;
use Thelia\Model\RewritingArgument;
use Thelia\Model\RewritingUrlQuery;

/**
 * Class RewritingResolver.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * This class provides methods to resolve rewritten URL as a query
 */
class RewritingResolver
{
    protected ?RewritingArgument $search = null;
    protected ?RewritingUrlQuery $rewritingUrlQuery = null;

    public mixed $view;
    public mixed $viewId;
    public mixed $locale;
    public mixed $otherParameters;
    public mixed $redirectedToUrl;
    public ?string $rewrittenUrl = null;

    public function __construct($url = null)
    {
        $this->rewritingUrlQuery = new RewritingUrlQuery();

        if ($url !== null) {
            $this->load($url);
        }
    }

    /**
     * @throws PropelException
     * @throws UrlRewritingException
     */
    public function load($rewrittenUrl): void
    {
        $rewrittenUrl = ltrim($rewrittenUrl, '/');
        $rewrittenUrl = urldecode($rewrittenUrl);
        $this->rewrittenUrl = $rewrittenUrl;
        $this->search = $this->rewritingUrlQuery->getResolverSearch($rewrittenUrl);

        if (!$this->search instanceof RewritingArgument) {
            throw new UrlRewritingException('URL NOT FOUND', UrlRewritingException::URL_NOT_FOUND);
        }
        $this->view = $this->search->getVirtualColumn('ru_view');
        $this->viewId = $this->search->getVirtualColumn('ru_viewId');
        $this->locale = $this->search->getVirtualColumn('ru_locale');
        $this->redirectedToUrl = $this->search->getVirtualColumn('ru_redirected_to_url');

        $this->otherParameters = $this->getOtherParameters();
    }

    /**
     * @throws UrlRewritingException
     */
    protected function getOtherParameters(): array
    {
        if ($this->search === null) {
            throw new UrlRewritingException('RESOLVER NULL SEARCH', UrlRewritingException::RESOLVER_NULL_SEARCH);
        }

        $otherParameters = [];
        foreach ($this->search as $result) {
            $parameter = $result->getParameter();
            $value = $result->getValue();

            if (null !== $parameter) {
                $otherParameters[$parameter] = $value;
            }
        }

        return $otherParameters;
    }
}
