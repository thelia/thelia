<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Core\Propel\Schema\Fixtures\Expectation;

/**
 * Expectations on the output of SchemaCombiner.
 */
abstract class SchemaCombineExpectation
{
    /**
     * Get expectations for SchemaCombinerInterface::combine.
     *
     * @return array
     */
    public static function getCombineExpectations()
    {
        // expectations on foo.schema.xml
        $fooTableExpectations = [
            'stuff' => [
                'source-file' => 'foo.schema.xml',
            ],
        ];

        // expectations on 1.bar.schema.xml
        $bar1TableExpectations = [
            'awesome_stuff' => [
                'source-file' => '1.bar.schema.xml',
                'name-prefixed-from' => 'stuff',
                'attributes' => [
                    'defaultAccessorVisibility' => [
                        'value' => 'public',
                        'source' => 'table',
                    ],
                    'defaultMutatorVisibility' => [
                        'value' => 'public',
                        'source' => 'table',
                    ],
                    'idMethod' => [
                        'value' => 'native',
                        'source' => 'database',
                    ],
                    'package' => [
                        'value' => 'my_little_db',
                        'source' => 'database',
                    ],
                    'namespace' => [
                        'value' => 'Acme\Bar\Model',
                        'source' => 'database',
                    ],
                    'schema' => [
                        'value' => 'a_schema',
                        'source' => 'database',
                    ],
                ],
            ],
            'awesome_thing' => [
                'source-file' => '1.bar.schema.xml',
                'name-prefixed-from' => 'thing',
                'attributes' => [
                    'idMethod' => [
                        'value' => 'none',
                        'source' => 'table',
                    ],
                    'defaultAccessorVisibility' => [
                        'value' => 'protected',
                        'source' => 'database',
                    ],
                    'defaultMutatorVisibility' => [
                        'value' => 'private',
                        'source' => 'database',
                    ],
                    'package' => [
                        'value' => 'my_little_db',
                        'source' => 'database',
                    ],
                    'namespace' => [
                        'value' => 'Acme\Bar\Model',
                        'source' => 'database',
                    ],
                    'schema' => [
                        'value' => 'a_schema',
                        'source' => 'database',
                    ],
                ],
            ],
        ];

        // expectations on 2.bar.schema.xml
        $bar2TableExpectations = [
            'cute_monster' => [
                'source-file' => '2.bar.schema.xml',
                'name-prefixed-from' => 'monster',
                'attributes' => [
                    'heavyIndexing' => [
                        'value' => 'true',
                        'source' => 'table',
                    ],
                    'baseClass' => [
                        'value' => 'Acme\Model',
                        'source' => 'database',
                    ],
                    'phpNamingMethod' => [
                        'value' => 'underscore',
                        'source' => 'database',
                    ],
                ],
            ],
        ];

        return [
            'no input' => [
                [],
                [],
            ],
            'foo' => [
                ['foo.schema.xml'],
                ['foo' => $fooTableExpectations],
            ],
            '1.bar' => [
                ['1.bar.schema.xml'],
                ['bar' => $bar1TableExpectations],
            ],
            '2.bar' => [
                ['2.bar.schema.xml'],
                ['bar' => $bar2TableExpectations],
            ],
            'foo + 1.bar + 2.bar' => [
                [
                    'foo.schema.xml',
                    '1.bar.schema.xml',
                    '2.bar.schema.xml',
                ],
                [
                    'foo' => $fooTableExpectations,
                    'bar' => array_merge($bar1TableExpectations, $bar2TableExpectations),
                ],
            ],
        ];
    }
}
