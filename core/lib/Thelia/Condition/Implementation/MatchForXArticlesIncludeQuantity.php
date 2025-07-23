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

namespace Thelia\Condition\Implementation;

use Thelia\Condition\Operators;

/**
 * Class MatchForXArticlesIncludeQuantity.
 *
 * @author Baixas Alban <abaixas@openstudio.fr>
 */
class MatchForXArticlesIncludeQuantity extends MatchForXArticles
{
    public function getServiceId(): string
    {
        return 'thelia.condition.match_for_x_articles_include_quantity';
    }

    public function getName(): string
    {
        return $this->translator->trans('Cart item include quantity count');
    }

    public function isMatching(): bool
    {
        return $this->conditionValidator->variableOpComparison(
            $this->facade->getNbArticlesInCartIncludeQuantity(),
            $this->operators[self::CART_QUANTITY],
            $this->values[self::CART_QUANTITY],
        );
    }

    public function drawBackOfficeInputs(): string
    {
        $labelQuantity = $this->facade->getTranslator()->trans('Cart item include quantity count is');

        return $this->drawBackOfficeBaseInputsText($labelQuantity, self::CART_QUANTITY);
    }

    public function getSummary(): string
    {
        $i18nOperator = Operators::getI18n(
            $this->translator,
            $this->operators[self::CART_QUANTITY],
        );

        return $this->translator->trans(
            'If cart item (include quantity) count is <strong>%operator%</strong> %quantity%',
            [
                '%operator%' => $i18nOperator,
                '%quantity%' => $this->values[self::CART_QUANTITY],
            ],
        );
    }
}
