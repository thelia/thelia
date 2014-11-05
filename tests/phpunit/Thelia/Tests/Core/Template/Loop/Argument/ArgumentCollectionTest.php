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
