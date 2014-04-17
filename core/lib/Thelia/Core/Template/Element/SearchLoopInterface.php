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
