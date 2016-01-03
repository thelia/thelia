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

use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

/**
 * This is the defualt Thelia controller, which is called when no controller was found to process the request.
 *
 * @author Manuel Raynaud <mraynadu@openstudio.fr>
 */

class DefaultController extends BaseFrontController
{
    /**
     * This is the default Thelia behaviour if no action is defined.
     *
     * If the request contains a 'view' parameter, this view will be displayed.
     * If the request contains a '_view' attribute (set in the route definition, for example), this view will be displayed.
     * Otherwise, we will use the "index" view.
     *
     * Additionaly, if the URL rewriting is enabled, the method will check if a redirect to the pâge rewritten URL should
     * be done.
     *
     * @param \Thelia\Core\HttpFoundation\Request $request
     * @throw RedirectException if a redirection to the rewritted URL shoud be done.
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
                    throw new RedirectException($rewrittenUrl->rewrittenUrl, 301);
                }
            }
        }
    }

    public function emptyRoute()
    {
        return new Response(null, 204);
    }
}
