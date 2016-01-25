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

namespace Thelia\Condition\Implementation;

use Thelia\Condition\Operators;

/**
 * Class MatchForXArticlesIncludeQuantity
 * @package Thelia\Condition\Implementation
 * @author Baixas Alban <abaixas@openstudio.fr>
 */
class MatchForXArticlesIncludeQuantity extends MatchForXArticles
{
    /**
     * @inheritdoc
     */
    public function getServiceId()
    {
        return 'thelia.condition.match_for_x_articles_include_quantity';
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->translator->trans('Cart item include quantity count');
    }

    /**
     * @inheritdoc
     */
    public function isMatching()
    {
        return $this->conditionValidator->variableOpComparison(
            $this->facade->getNbArticlesInCartIncludeQuantity(),
            $this->operators[self::CART_QUANTITY],
            $this->values[self::CART_QUANTITY]
        );
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        $labelQuantity = $this->facade->getTranslator()->trans('Cart item include quantity count is');

        return $this->drawBackOfficeBaseInputsText($labelQuantity, self::CART_QUANTITY);
    }

    /**
     * @inheritdoc
     */
    public function getSummary()
    {
        $i18nOperator = Operators::getI18n(
            $this->translator,
            $this->operators[self::CART_QUANTITY]
        );

        $toolTip = $this->translator->trans(
            'If cart item (include quantity) count is <strong>%operator%</strong> %quantity%',
            array(
                '%operator%' => $i18nOperator,
                '%quantity%' => $this->values[self::CART_QUANTITY]
            )
        );

        return $toolTip;
    }

}
