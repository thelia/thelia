<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
