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
namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\RewritingUrl as BaseRewritingUrl;

class RewritingUrl extends BaseRewritingUrl
{
    public function postInsert(ConnectionInterface $con = null): void
    {
        parent::postInsert($con);

        if (null !== $this->getRedirected()) {
            // check if rewriting url alredy exists and put redirect to the new one
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
}
