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
/**
 * The parser context is an application-wide context, which stores var-value pairs.
 * Theses pairs are injected in the parser and becomes available to the templates.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ParserContext implements \IteratorAggregate
{
	private $store = array();

	public function __construct(Request $request) {

		// Setup basic variables
		$this
			->set('base_url'		, ConfigQuery::read('base_url', '/'))
			->set('index_page'		, URL::getIndexPage())
			->set('return_to_url'	, URL::absoluteUrl($request->getSession()->getReturnToUrl()))
		;
	}

	public function set($name, $value) {
		$this->store[$name] = $value;

		return $this;
	}

	public function get($name) {
		return $this->store[$name];
	}

	public function getIterator() {
		return new \ArrayIterator( $this->store );
	}
}
