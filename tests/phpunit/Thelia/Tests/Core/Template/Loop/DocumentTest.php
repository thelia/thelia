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

use Thelia\Tests\Core\Template\Element\BaseLoopTestor;
use Thelia\Core\Template\Loop\Document;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\CategoryDocumentQuery;
use Thelia\Model\ContentDocumentQuery;
use Thelia\Model\FolderDocumentQuery;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class DocumentTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\Document';
    }

    public function getTestedInstance()
    {
        return new Document($this->container);
    }

    public function getMandatoryArguments()
    {
        return array('source' => 'product', 'id' => 1);
    }

    public function testSearchByProductId()
    {
        $document = ProductDocumentQuery::create()->findOne();

        $this->baseTestSearchById($document->getId(), array('source' => 'product'));
    }

    public function testSearchByFolderId()
    {
        $document = FolderDocumentQuery::create()->findOne();

        $this->baseTestSearchById($document->getId(), array('source' => 'folder'));
    }

    public function testSearchByContentId()
    {
        $document = ContentDocumentQuery::create()->findOne();

        $this->baseTestSearchById($document->getId(), array('source' => 'content'));
    }

    public function testSearchByCategoryId()
    {
        $document = CategoryDocumentQuery::create()->findOne();

        $this->baseTestSearchById($document->getId(), array('source' => 'category'));
    }
}
