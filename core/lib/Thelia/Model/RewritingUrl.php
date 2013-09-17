<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\RewritingUrl as BaseRewritingUrl;
use Thelia\Model\RewritingUrlQuery;

class RewritingUrl extends BaseRewritingUrl {

    public function preSave(ConnectionInterface $con = null)
    {
        if($this->getRedirected() == 0) {
            //check if rewriting url alredy exists and put redirect to 1
            RewritingUrlQuery::create()
                ->filterByView($this->getView())
                ->filterByViewId($this->getViewId())
                ->filterByViewLocale($this->getViewLocale())
                ->update(array(
                    "redirect" => 1
                ));
        }

        return true;
    }
}
