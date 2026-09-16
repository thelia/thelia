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

namespace Thelia\Tests\Unit\Domain\Media;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Media\AltTextResolver;

final class AltTextResolverTest extends TestCase
{
    private AltTextResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new AltTextResolver();
    }

    public function testAltTextIsUsedWhenFilledIn(): void
    {
        self::assertSame('Sac en cuir', $this->resolver->resolve('Sac en cuir', false, 'Sac'));
    }

    public function testTitleIsUsedWhenAltTextIsEmpty(): void
    {
        self::assertSame('Sac', $this->resolver->resolve('', false, 'Sac'));
    }

    public function testDecorativeAlwaysResolvesToAnEmptyStringEvenWithAnAltText(): void
    {
        self::assertSame('', $this->resolver->resolve('Sac en cuir', true, 'Sac'));
    }

    public function testEverythingMissingResolvesToAnEmptyString(): void
    {
        self::assertSame('', $this->resolver->resolve(null, false, null));
    }

    public function testAnAltTextMadeOfOnlyWhitespaceFallsBackToTheTitle(): void
    {
        self::assertSame('Sac', $this->resolver->resolve('   ', false, 'Sac'));
    }
}
