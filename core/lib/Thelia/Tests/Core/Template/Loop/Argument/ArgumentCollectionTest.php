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

namespace Thelia\Tests\Core\Template\Loop\Argument;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class ArgumentCollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testArgumentCollectionCreateAndWalk()
    {
        $collection = new ArgumentCollection(
            new Argument(
                'arg0',
                new TypeCollection(
                    new Type\AnyType()
                )
            ),
            new Argument(
                'arg1',
                new TypeCollection(
                    new Type\AnyType()
                )
            )
        );

        $collection->addArgument(
            new Argument(
                'arg2',
                new TypeCollection(
                    new Type\AnyType()
                )
            )
        );

        $this->assertTrue($collection->getCount() == 3);

        $this->assertTrue($collection->key() == 'arg0');
        $collection->next();
        $this->assertTrue($collection->key() == 'arg1');
        $collection->next();
        $this->assertTrue($collection->key() == 'arg2');
        $collection->next();

        $this->assertFalse($collection->valid());

        $collection->rewind();
        $this->assertTrue($collection->key() == 'arg0');
    }

    public function testArgumentCollectionFetch()
    {
        $collection = new ArgumentCollection(
            new Argument(
                'arg0',
                new TypeCollection(
                    new Type\AnyType()
                )
            ),
            new Argument(
                'arg1',
                new TypeCollection(
                    new Type\AnyType()
                )
            ),
            new Argument(
                'arg2',
                new TypeCollection(
                    new Type\AnyType()
                )
            )
        );

        $arguments = \PHPUnit_Framework_Assert::readAttribute($collection, 'arguments');

        foreach ($collection as $key => $argument) {
            $this->assertEquals(
                $argument,
                $arguments[$key]
            );
        }
    }
}
