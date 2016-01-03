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

namespace Thelia\Tests\ImportExport\Export;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Core\FileFormat\FormatType;
use Thelia\Model\AddressQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Lang;
use Thelia\Tests\ContainerAwareTestCase;

/**
 * Class ExportHandlerTest
 * @package Thelia\Tests\ImportExport\Export
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ExportHandlerTest extends ContainerAwareTestCase
{
    /** @var  \Thelia\ImportExport\Export\ExportHandler */
    protected $handler;

    /**
     * Use this method to build the container with the services that you need.
     */
    protected function buildContainer(ContainerBuilder $container)
    {
        $container->setParameter(
            "Thelia.parser.loops",
            [
                "address" => "Thelia\\Core\\Template\\Loop\\Address",
            ]
        );
    }

    public function setUp()
    {
        parent::setUp();

        $this->handler = $this->getMock(
            "Thelia\\ImportExport\\Export\\ExportHandler",
            [
                "getHandledTypes",
                "buildDataSet"
            ],
            [
                $this->container
            ]
        );

        $this->handler->expects($this->any())
            ->method("getHandledTypes")
            ->willReturn([FormatType::TABLE, FormatType::UNBOUNDED])
        ;
    }

    public function testRenderLoop()
    {
        $customerId = CustomerQuery::create()
            ->findOne()
            ->getId();

        $this->handler
            ->expects($this->any())
            ->method("buildDataSet")
            ->willReturn($this->handler->renderLoop("address", ["customer"=>$customerId]))
        ;

        $lang = Lang::getDefaultLanguage();

        $loop = $this->handler->buildDataSet($lang);

        $this->assertInstanceOf(
            "Thelia\\Core\\Template\\Loop\\Address",
            $loop
        );

        $data = $this->handler->buildData($lang);

        $addresses = AddressQuery::create()
            ->filterByCustomerId($customerId)
            ->find()
            ->toArray("Id")
        ;

        foreach ($data->getData() as $row) {
            $this->assertArrayHasKey("id", $row);

            $this->assertArrayHasKey($row["id"], $addresses);

            $this->assertEquals(count($addresses), $row["loop_total"]);

            $address = $addresses[$row["id"]];

            $this->assertEquals($row["address1"], $address["Address1"]);
            $this->assertEquals($row["address2"], $address["Address2"]);
            $this->assertEquals($row["address3"], $address["Address3"]);
            $this->assertEquals($row["cellphone"], $address["Cellphone"]);
            $this->assertEquals($row["city"], $address["City"]);
            $this->assertEquals($row["company"], $address["Company"]);
            $this->assertEquals($row["country"], $address["CountryId"]);
            $this->assertEquals($row["create_date"], $address["CreatedAt"]);
            $this->assertEquals($row["update_date"], $address["UpdatedAt"]);
            $this->assertEquals($row["firstname"], $address["Firstname"]);
            $this->assertEquals($row["lastname"], $address["Lastname"]);
            $this->assertEquals($row["id"], $address["Id"]);
            $this->assertEquals($row["label"], $address["Label"]);
            $this->assertEquals($row["phone"], $address["Phone"]);
            $this->assertEquals($row["title"], $address["TitleId"]);
            $this->assertEquals($row["zipcode"], $address["Zipcode"]);
        }
    }
}
