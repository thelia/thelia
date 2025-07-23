<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tools\Version\Constraints;

/**
 * Class BaseConstraint.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
abstract class BaseConstraint implements ConstraintInterface
{
    protected $operator = '=';

    public function __construct(protected $expression)
    {
    }

    public function test($version, $strict = false): bool
    {
        $version = $this->normalize($version, $strict);

        return version_compare($version, $this->expression, $this->operator);
    }

    public function normalize($version, $strict = false): string
    {
        return $strict ? $version : $this->normalizePrecision($version);
    }

    protected function normalizePrecision($version, $changeExpression = true)
    {
        $expressionElements = preg_split('/[\.\-]/', (string) $this->expression);
        // cleaning alpha RC beta
        $version = preg_replace('/\-.*$/', '', (string) $version);
        $versionElements = preg_split('/\./', (string) $version);

        if (\count($expressionElements) < \count($versionElements)) {
            if (true === $changeExpression) {
                $this->expression = implode(
                    '.',
                    array_merge(
                        $expressionElements,
                        array_fill(
                            \count($expressionElements) - 1,
                            \count($versionElements) - \count($expressionElements),
                            '0',
                        ),
                    ),
                );
            } else {
                $version = implode(
                    '.',
                    \array_slice($versionElements, 0, \count($expressionElements)),
                );
            }
        }

        return $version;
    }
}
