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
    public function testTypeCollectionConstruction()
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

    public function testTypeCollectionFetch()
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

    public function testTypes()
    {
        $anyType = new Type\AnyType();
        $this->assertTrue($anyType->isValid(md5(rand(1000, 10000))));

        $intType = new Type\IntType();
        $this->assertTrue($intType->isValid('1'));
        $this->assertTrue($intType->isValid(2));
        $this->assertFalse($intType->isValid('3.3'));

        $floatType = new Type\FloatType();
        $this->assertTrue($floatType->isValid('1.1'));
        $this->assertTrue($floatType->isValid(2.2));
        $this->assertFalse($floatType->isValid('foo'));

        $enumType = new Type\EnumType(array("cat", "dog"));
        $this->assertTrue($enumType->isValid('cat'));
        $this->assertTrue($enumType->isValid('dog'));
        $this->assertFalse($enumType->isValid('monkey'));
        $this->assertFalse($enumType->isValid('catdog'));

        $intListType = new Type\IntListType();
        $this->assertTrue($intListType->isValid('1'));
        $this->assertTrue($intListType->isValid('1,2,3'));
        $this->assertFalse($intListType->isValid('1,2,3.3'));

        $jsonType = new Type\JsonType();
        $this->assertTrue($jsonType->isValid('{"k0":"v0","k1":"v1","k2":"v2"}'));
        $this->assertFalse($jsonType->isValid('1,2,3'));
    }
}
