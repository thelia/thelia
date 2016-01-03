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

use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\Translation\Translator;
use Thelia\Core\FileFormat\FormatType;
use Thelia\ImportExport\Export\Type\MailingExport;
use Thelia\Model\Lang;

/**
 * Class MailingExportTest
 * @package Thelia\Tests\ImportExport\Export
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class MailingExportTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Thelia\ImportExport\Export\Type\MailingExport $handler */
    protected $handler;

    public function setUp()
    {
        $container = new Container();

        new Translator($container);

        $this->handler = new \Thelia\ImportExport\Export\Type\MailingExport($container);
    }

    public function testExport()
    {
        $data = $this->handler->buildData(Lang::getDefaultLanguage());
    }

    public function testType()
    {
        $this->assertEquals(
            [\Thelia\Core\FileFormat\FormatType::TABLE, FormatType::UNBOUNDED],
            $this->handler->getHandledTypes()
        );
    }
}
