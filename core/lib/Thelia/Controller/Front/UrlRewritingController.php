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

use Thelia\Core\HttpFoundation\Request;
use Thelia\Exception\UrlRewritingException;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

class UrlRewritingController extends BaseFrontController
{
    public function check(Request $request, $rewritten_url)
    {
        if(ConfigQuery::isRewritingEnable()) {
            try {
                $rewrittenUrlData = URL::getInstance()->resolve($rewritten_url);
            } catch(UrlRewritingException $e) {
                switch($e->getCode()) {
                    case UrlRewritingException::URL_NOT_FOUND :
                        return $this->pageNotFound();
                        break;
                    default:
                        throw $e;
                }
            }

            /* is the URL redirected ? */

            if(null !== $rewrittenUrlData->redirectedToUrl) {
                $this->redirect($rewrittenUrlData->redirectedToUrl, 301);
            }

            /* define GET arguments in request */

            if(null !== $rewrittenUrlData->view) {
                $request->query->set('view', $rewrittenUrlData->view);
                if(null !== $rewrittenUrlData->viewId) {
                    $request->query->set($rewrittenUrlData->view . '_id', $rewrittenUrlData->viewId);
                }
            }
            if(null !== $rewrittenUrlData->locale) {
                $request->query->set('locale', $rewrittenUrlData->locale);
            }

            foreach($rewrittenUrlData->otherParameters as $parameter => $value) {
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
