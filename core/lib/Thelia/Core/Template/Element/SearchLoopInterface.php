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
namespace Thelia\Core\Template\Element;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
interface SearchLoopInterface
{
    const MODE_ANY_WORD = 'any_word';
    const MODE_SENTENCE = 'sentence';
    const MODE_STRICT_SENTENCE = 'strict_sentence';

    /**
     * @return array of available field to search in
     */
    public function getSearchIn();

    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria);
}
