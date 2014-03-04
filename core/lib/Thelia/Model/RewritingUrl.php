<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\RewritingUrl as BaseRewritingUrl;

class RewritingUrl extends BaseRewritingUrl
{
    public function postInsert(ConnectionInterface $con = null)
    {
        if (null !== $this->getRedirected()) {
            //check if rewriting url alredy exists and put redirect to the new one
            RewritingUrlQuery::create()
                ->filterByView($this->getView())
                ->filterByViewId($this->getViewId())
                ->filterByViewLocale($this->getViewLocale())
                ->filterByRedirected($this->getId(), Criteria::NOT_IN)
                ->update(array(
                    "Redirected" => $this->getId()
                ));
        }
    }
}
