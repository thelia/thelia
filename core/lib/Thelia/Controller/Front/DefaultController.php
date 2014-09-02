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

namespace Thelia\Controller\Front;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Model\ConfigQuery;

use Thelia\Tools\URL;

/**
 *
 * Must be the last controller call. It fixes default values
 *
 * @author Manuel Raynaud <mraynadu@openstudio.fr>
 */

class DefaultController extends BaseFrontController
{
    /**
     *
     * set the default value for thelia
     *
     * In this case there is no action so we have to verify if some needed params are not missing
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function noAction(Request $request)
    {
        $view = null;

        if (! $view = $request->query->get('view')) {
            if ($request->request->has('view')) {
                $view = $request->request->get('view');
            }
        }
        if (null !== $view) {
            $request->attributes->set('_view', $view);
        }

        if (null === $view && null === $request->attributes->get("_view")) {
            $request->attributes->set("_view", "index");
        }

        if (ConfigQuery::isRewritingEnable()) {
            if ($request->attributes->get('_rewritten', false) === false) {
                /* Does the query GET parameters match a rewritten URL ? */
                $rewrittenUrl = URL::getInstance()->retrieveCurrent($request);
                if ($rewrittenUrl->rewrittenUrl !== null) {
                    /* 301 redirection to rewritten URL */

                    return $this->generateRedirect($rewrittenUrl, 301);
                }
            }
        }
    }

    public function emptyRoute()
    {
        return new Response(null, 204);
    }
}
