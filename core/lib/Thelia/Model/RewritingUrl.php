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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\RewritingUrl as BaseRewritingUrl;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Tools\URL;

class RewritingUrl extends BaseRewritingUrl
{
    public function postInsert(ConnectionInterface $con = null): void
    {
        parent::postInsert($con);

        if (null !== $this->getRedirected()) {
            //check if rewriting url alredy exists and put redirect to the new one
            RewritingUrlQuery::create()
                ->filterByView($this->getView())
                ->filterByViewId($this->getViewId())
                ->filterByViewLocale($this->getViewLocale())
                ->filterByRedirected($this->getId(), Criteria::NOT_IN)
                ->update([
                    'Redirected' => $this->getId(),
                ]);
        }
    }

    /**
     * Set the value of [url] column.
     *
     * @param string $v New value
     * @return \Thelia\Model\RewritingUrl The current object (for fluent API support)
     */
    public function setUrl($v) : self
    {
        if ($v !== null) {
            $v = (string) $v;

            if (ConfigQuery::isSeoTransliteratorEnable() == 1)
            {
                $v = URL::sanitize($v);
            }
        }

        if ($this->url !== $v) {
            $this->url = $v;
            $this->modifiedColumns[RewritingUrlTableMap::COL_URL] = true;
        }

        return $this;
    } // setUrl()
}
