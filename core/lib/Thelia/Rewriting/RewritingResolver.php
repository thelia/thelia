<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Rewriting;

use Thelia\Exception\UrlRewritingException;
use Thelia\Model\RewritingUrlQuery;

/**
 * Class RewritingResolver
 * @package Thelia\Rewriting
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * This class provides methods to resolve rewritten URL as a query
 */
class RewritingResolver
{
    protected $search = null;
    protected $rewritingUrlQuery = null;

    public $view;
    public $viewId;
    public $locale;
    public $otherParameters;
    public $redirectedToUrl;
    public $rewrittenUrl;

    public function __construct($url = null)
    {
        $this->rewritingUrlQuery = new RewritingUrlQuery();

        if ($url !== null) {
            $this->load($url);
        }
    }

    public function load($rewrittenUrl)
    {
        $rewrittenUrl = ltrim($rewrittenUrl, '/');
        $rewrittenUrl = urldecode($rewrittenUrl);
        $this->rewrittenUrl = $rewrittenUrl;
        $this->search = $this->rewritingUrlQuery->getResolverSearch($rewrittenUrl);

        if ($this->search->count() == 0) {
            throw new UrlRewritingException('URL NOT FOUND', UrlRewritingException::URL_NOT_FOUND);
        }

        $this->view = $this->search->getFirst()->getVirtualColumn('ru_view');
        $this->viewId = $this->search->getFirst()->getVirtualColumn('ru_viewId');
        $this->locale = $this->search->getFirst()->getVirtualColumn('ru_locale');
        $this->redirectedToUrl = $this->search->getFirst()->getVirtualColumn('ru_redirected_to_url');

        $this->otherParameters = $this->getOtherParameters();
    }

    protected function getOtherParameters()
    {
        if ($this->search === null) {
            throw new UrlRewritingException('RESOLVER NULL SEARCH', UrlRewritingException::RESOLVER_NULL_SEARCH);
        }

        $otherParameters = array();
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
