<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Element;

use Propel\Runtime\ActiveQuery\ModelCriteria;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
interface SearchLoopInterface
{
    public const MODE_ANY_WORD = 'any_word';
    public const MODE_SENTENCE = 'sentence';
    public const MODE_STRICT_SENTENCE = 'strict_sentence';

    /**
     * @return array of available field to search in
     */
    public function getSearchIn();

    /**
     * @param ModelCriteria $search a query
     * @param string $searchTerm the searched term
     * @param array $searchIn available field to search in
     * @param string $searchCriteria the search criteria, such as Criterial::LIKE, Criteria::EQUAL, etc.
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria);
}
