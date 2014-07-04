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

namespace Thelia\Tests\FileFormat\Formatter;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\FileFormat\Formatter\FormatterData;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\ProductQuery;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Product;

/**
 * Class FormatterDataTest
 * @package Thelia\Tests\FileFormat\Formatter
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class FormatterDataTest extends \PHPUnit_Framework_TestCase
{

    protected $data;

    public function setUp()
    {
        new Translator(new Container());
        $this->data = new FormatterData();

        $query = ProductQuery::create()
            ->filterById([3,4,5], Criteria::IN);

        $this->data->loadModelCriteria($query);

        $query = ProductSaleElementsQuery::create()
            ->joinProduct()
            ->select(["ProductSaleElements.id", "Product.id"])
            ->filterById([3,4,5], Criteria::IN)
        ;

        $this->data->loadModelCriteria($query);

        $query = ProductSaleElementsQuery::create()
            ->joinProduct()
            ->select(["ProductSaleElements.id"])
            ->filterById([3,4,5], Criteria::IN)
        ;

        $this->data->loadModelCriteria($query);

        $query = ProductQuery::create()
            ->joinProductSaleElements()
            ->filterById([3,4,5], Criteria::IN);

        $this->data->loadModelCriteria($query);
    }

    public function testA()
    {

    }
}
