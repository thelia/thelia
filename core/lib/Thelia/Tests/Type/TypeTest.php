<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Tests\Type;

use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class TypeTest extends \PHPUnit_Framework_TestCase
{
    function testTypeCollectionConstruction()
    {
        $collection = new TypeCollection(
            new Type\AnyType(),
            new Type\AnyType()
        );

        $collection->addType(
            new Type\AnyType()
        );

        $this->assertAttributeEquals(
            array(
                new Type\AnyType(),
                new Type\AnyType(),
                new Type\AnyType(),
            ),
            'types',
            $collection
        );
    }

    function testTypeCollectionFetch()
    {
        $collection = new TypeCollection(
            new Type\AnyType(),
            new Type\AnyType(),
            new Type\AnyType()
        );


        $types = \PHPUnit_Framework_Assert::readAttribute($collection, 'types');

        $collection->rewind();
        while ($collection->valid()) {

            $type = $collection->current();

            $this->assertEquals(
                $type,
                $types[$collection->key()]
            );

            $collection->next();
        }
    }
}
