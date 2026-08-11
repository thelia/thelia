<?php

declare(strict_types=1);

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
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\Lang;
use Thelia\Model\Tools\ModelCriteriaTools;

/**
 * Class BaseI18nLoop, imlplemented by loops providing internationalized data, such as title, description, etc.
 *
 * @method string getLang()
 */
abstract class BaseI18nLoop extends BaseLoop
{
    protected $locale;

    /**
     * Define common loop arguments.
     *
     * @return Argument[]
     */
    protected function getDefaultArgs(): array
    {
        $args = parent::getDefaultArgs();

        $args[] = Argument::createAnyTypeArgument('lang');

        return $args;
    }

    /**
     * Setup ModelCriteria for proper i18n processing.
     *
     * @param ModelCriteria $search       the Propel Criteria to configure
     * @param array         $columns      the i18n columns
     * @param string|null   $foreignTable the specified table (default  to criteria table)
     * @param string        $foreignKey   the foreign key in this table (default to criteria table)
     *
     * @return mixed the locale
     */
    protected function configureI18nProcessing(
        ModelCriteria $search,
        array $columns = ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'],
        ?string $foreignTable = null,
        string $foreignKey = 'ID',
        bool $forceReturn = false,
    ): void {
        /* manage translations */
        /** @var Lang $lang */
        $lang = $this->getMainRequest()->getSession()->getLang() ?? Lang::getDefaultLanguage();
        $this->locale = ModelCriteriaTools::getI18n(
            $this->getBackendContext(),
            $lang->getId(),
            $search,
            $lang->getLocale(),
            $columns,
            $foreignTable,
            $foreignKey,
            $this->getForceReturn(),
        );
    }

    /**
     * Build the SQL placeholder(s) and PDO binding type for a search term. When $searchTerm is a list of values
     * (search_mode=any_word uses Criteria::IN with several words), a single "?" cannot be bound to it: one
     * placeholder per value is required instead, and Propel must be left to auto-detect the binding type.
     *
     * @return array{0: string|array, 1: string, 2: int|null} the (possibly normalized) search term, the SQL
     *                                                        placeholder, and the PDO binding type to use
     */
    protected function buildSearchTermPlaceholder(string|array $searchTerm): array
    {
        if (\is_array($searchTerm) && 1 === \count($searchTerm)) {
            $searchTerm = reset($searchTerm);
        }

        if (\is_array($searchTerm)) {
            return [$searchTerm, '('.implode(', ', array_fill(0, \count($searchTerm), '?')).')', null];
        }

        return [$searchTerm, '?', \PDO::PARAM_STR];
    }

    /**
     * Add the search clause for an I18N column, taking care of the back/front context, as default_locale_i18n is
     * not defined in the backEnd I18N context.
     *
     * @param string       $columnName     the column to search into, such as TITLE
     * @param string       $searchCriteria the search criteria, such as Criterial::LIKE, Criteria::EQUAL, etc
     * @param string|array $searchTerm     the searched term, or a list of terms when $searchCriteria is Criteria::IN
     */
    public function addSearchInI18nColumn(ModelCriteria $search, string $columnName, string $searchCriteria, string|array $searchTerm): void
    {
        [$searchTerm, $placeholder, $bindingType] = $this->buildSearchTermPlaceholder($searchTerm);

        if (!$this->getBackendContext()) {
            $search->where(
                "CASE WHEN NOT ISNULL(`requested_locale_i18n`.ID)
                        THEN `requested_locale_i18n`.`{$columnName}`
                        ELSE `default_locale_i18n`.`{$columnName}`
                        END ".$searchCriteria.' '.$placeholder,
                $searchTerm,
                $bindingType,
            );
        } else {
            $search->where(
                \sprintf('`requested_locale_i18n`.`%s` %s %s', $columnName, $searchCriteria, $placeholder),
                $searchTerm,
                $bindingType,
            );
        }
    }
}
