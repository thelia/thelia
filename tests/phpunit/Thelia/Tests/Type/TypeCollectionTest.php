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

namespace Thelia\Tests\Type;

use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class TypeCollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testTypeCollectionConstruction()
    {
        $collection = new TypeCollection(
            new Type\AnyType(),
            new Type\IntType()
        );

        $collection->addType(
            new Type\FloatType()
        );

        $this->assertAttributeEquals(
            array(
                new Type\AnyType(),
                new Type\IntType(),
                new Type\FloatType(),
            ),
            'types',
            $collection
        );
    }

    public function testTypeCollectionFetch()
    {
        $collection = new TypeCollection(
            new Type\AnyType(),
            new Type\IntType(),
            new Type\FloatType()
        );

        $types = \PHPUnit_Framework_Assert::readAttribute($collection, 'types');

        foreach ($collection as $key => $type) {
            $this->assertEquals(
                $type,
                $types[$key]
            );
        }
    }
}
