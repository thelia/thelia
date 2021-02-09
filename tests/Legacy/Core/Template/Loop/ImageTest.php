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

namespace Thelia\Tests\Core\Template\Loop;

use Thelia\Core\Template\Loop\Image;
use Thelia\Model\CategoryImageQuery;
use Thelia\Model\ContentImageQuery;
use Thelia\Model\FolderImageQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class ImageTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\Image';
    }

    public function getMandatoryArguments()
    {
        return ['source' => 'product', 'id' => 1];
    }

    public function testSearchByProductId()
    {
        $image = ProductImageQuery::create()->findOne();

        $this->baseTestSearchById($image->getId(), ['source' => 'product']);
    }

    public function testSearchByFolderId()
    {
        $image = FolderImageQuery::create()->findOne();

        $this->baseTestSearchById($image->getId(), ['source' => 'folder']);
    }

    public function testSearchByContentId()
    {
        $image = ContentImageQuery::create()->findOne();

        $this->baseTestSearchById($image->getId(), ['source' => 'content']);
    }

    public function testSearchByCategoryId()
    {
        $image = CategoryImageQuery::create()->findOne();

        $this->baseTestSearchById($image->getId(), ['source' => 'category']);
    }
}
