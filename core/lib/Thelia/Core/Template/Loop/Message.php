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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Message as MessageModel;
use Thelia\Model\MessageQuery;
use Thelia\Type\BooleanOrBothType;

/**
 * Message loop, to access messageuration variables.
 *
 * - id is the message id
 * - name is the message name
 * - hidden filters by hidden status (yes, no, both)
 * - secured filters by secured status (yes, no, both)
 * - exclude is a comma separated list of message IDs that will be excluded from output
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int         getId()
 * @method int[]       getExclude()
 * @method string      getVariable()
 * @method bool|string getHidden()
 * @method bool|string getSecured()
 */
class Message extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createAnyTypeArgument('variable'),
            Argument::createBooleanOrBothTypeArgument('hidden'),
            Argument::createBooleanOrBothTypeArgument('secured'),
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $id = $this->getId();
        $name = $this->getVariable();
        $secured = $this->getSecured();
        $exclude = $this->getExclude();

        $search = MessageQuery::create();

        $this->configureI18nProcessing(
            $search,
            [
                'TITLE',
                'SUBJECT',
                'TEXT_MESSAGE',
                'HTML_MESSAGE',
            ],
        );

        if (null !== $id) {
            $search->filterById($id);
        }

        if (null !== $name) {
            $search->filterByName($name);
        }

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        if (null !== $secured && BooleanOrBothType::ANY !== $secured) {
            $search->filterBySecured($secured ? 1 : 0);
        }

        $search->orderByName(Criteria::ASC);

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var MessageModel $result */
        foreach ($loopResult->getResultDataCollection() as $result) {
            $loopResultRow = new LoopResultRow($result);

            $loopResultRow
                ->set('ID', $result->getId())
                ->set('NAME', $result->getName())
                ->set('IS_TRANSLATED', $result->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('TITLE', $result->getVirtualColumn('i18n_TITLE'))
                ->set('SUBJECT', $result->getVirtualColumn('i18n_SUBJECT'))
                ->set('TEXT_MESSAGE', $result->getVirtualColumn('i18n_TEXT_MESSAGE'))
                ->set('HTML_MESSAGE', $result->getVirtualColumn('i18n_HTML_MESSAGE'))
                ->set('SECURED', $result->getSecured());
            $this->addOutputFields($loopResultRow, $result);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
