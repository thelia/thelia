<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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

//use Propel\Runtime\Exception\PropelException;
//use Thelia\Form\Exception\FormValidationException;
//use Thelia\Core\Event\CartEvent;
//use Thelia\Core\Event\TheliaEvents;
//use Symfony\Component\HttpFoundation\Request;
//use Thelia\Form\CartAdd;

use Thelia\Core\HttpFoundation\Request;
use Thelia\Exception\UrlRewritingException;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

class UrlRewritingController extends BaseFrontController
{
    public function check(Request $request)
    {
        if(ConfigQuery::isRewritingEnable()) {
            try {
                $rewrittentUrlData = URL::resolveCurrent($request);
            } catch(UrlRewritingException $e) {
                $code = $e->getCode();
                switch($e->getCode()) {
                    case UrlRewritingException::URL_NOT_FOUND :
                        /* TODO : redirect 404 */
                        throw $e;
                        break;
                    default:
                        throw $e;
                }
            }

            /* define GET arguments in request */

            if(null !== $rewrittentUrlData->view) {
                $request->query->set('view', $rewrittentUrlData->view);
                if(null !== $rewrittentUrlData->viewId) {
                    $request->query->set($rewrittentUrlData->view . '_id', $rewrittentUrlData->viewId);
                }
            }
            if(null !== $rewrittentUrlData->locale) {
                $request->query->set('locale', $rewrittentUrlData->locale);
            }

            foreach($rewrittentUrlData->otherParameters as $parameter => $value) {
                $request->query->set($parameter, $value);
            }
        }

        if (! $view = $request->query->get('view')) {
            $view = "index";
            if ($request->request->has('view')) {
                $view = $request->request->get('view');
            }
        }

        $request->attributes->set('_view', $view);

    }

}
