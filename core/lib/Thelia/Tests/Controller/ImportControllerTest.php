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

namespace Thelia\Tests\Controller;
use Thelia\Controller\Admin\ImportController;

/**
 * Class ImportControllerTest
 * @package Thelia\Tests\Controller
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImportControllerTest extends ImportExportControllerTest
{
    /**
     * @return \Thelia\Controller\BaseController The controller you want to test
     */
    protected function getController()
    {
        return new ImportController();
    }
    

} 