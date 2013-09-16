<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Controller\Front;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\Redirect;
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
                    $this->redirect($rewrittenUrl->rewrittenUrl, 301);
                }
            }
        }
    }
}
