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

use InvalidArgumentException;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;

/**
 * Brand loop.
 *
 * Class ModuleConfig
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method string getModule()
 * @method string getVariable()
 * @method string getDefaultValue()
 * @method string getLocale()
 */
class ModuleConfig extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createAnyTypeArgument('module', null, true),
            Argument::createAnyTypeArgument('variable', null, true),
            Argument::createAnyTypeArgument('default_value', null),
            Argument::createAnyTypeArgument('locale', null)
        );
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        $moduleCode = $this->getModule();

        if (null === $module = ModuleQuery::create()->filterByCode($moduleCode, Criteria::LIKE)->findOne()) {
            throw new InvalidArgumentException(sprintf("Module with code '%s' does not exists.", $moduleCode));
        }

        $configValue = ModuleConfigQuery::create()->getConfigValue(
            $module->getId(),
            $this->getVariable(),
            $this->getDefaultValue(),
            $this->getLocale()
        );

        $loopResultRow = new LoopResultRow();

        $loopResultRow
            ->set('VARIABLE', $this->getVariable())
            ->set('VALUE', $configValue)
        ;

        $loopResult->addRow($loopResultRow);

        return $loopResult;
    }

    /**
     * this method returns an array.
     */
    public function buildArray(): array
    {
        // Return an array containing one element, so that parseResults() will be called one time.
        return ['dummy-element'];
    }
}
