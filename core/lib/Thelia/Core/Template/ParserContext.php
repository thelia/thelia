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

namespace Thelia\Core\Template;

use Thelia\Model\ConfigQuery;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Tools\URL;
use Thelia\Form\BaseForm;
/**
 * The parser context is an application-wide context, which stores var-value pairs.
 * Theses pairs are injected in the parser and becomes available to the templates.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ParserContext implements \IteratorAggregate
{
    private $store = array();

    public function __construct(Request $request)
    {
        // Setup basic variables
        $this
            ->set('BASE_URL'		, ConfigQuery::read('base_url', '/'))
            ->set('INDEX_PAGE'		, URL::getIndexPage())
            ->set('RETURN_TO_URL'	, URL::absoluteUrl($request->getSession()->getReturnToUrl()))
            ->set('THELIA_VERSION'	, ConfigQuery::read('thelia_version', 'undefined'))
        ;
    }

    // -- Error form -----------------------------------------------------------

    /**
     * @param BaseForm $form the errored form
     */
    public function setErrorForm(BaseForm $form)
    {
        $this->set('error_form', $form);
    }

    public function getErrorForm()
    {
        return $this->get('error_form', null);
    }

    public function clearErrorForm()
    {
        return $this->remove('error_form');
    }

    // -- Internal table manipulation ------------------------------------------

    public function set($name, $value)
    {
        $this->store[$name] = $value;

        return $this;
    }

    public function remove($name)
    {
        unset($this->store[$name]);

        return $this;
    }

    public function get($name, $default = null)
    {
        return isset($this->store[$name]) ? $this->store[$name] : $default;
    }

    public function getIterator()
    {
        return new \ArrayIterator( $this->store );
    }
}
