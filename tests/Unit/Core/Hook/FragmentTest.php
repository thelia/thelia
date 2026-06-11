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

namespace Thelia\Tests\Unit\Core\Hook;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Hook\Fragment;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class FragmentTest extends TestCase
{
    public function testIssetSeesFragmentData(): void
    {
        $fragment = new Fragment(['url' => 'https://example.com', 'title' => 'Tools']);

        $this->assertTrue(isset($fragment->url));
        $this->assertTrue(isset($fragment->title));
        $this->assertFalse(isset($fragment->missing));
    }

    public function testTwigCanReadFragmentAttributes(): void
    {
        $twig = new Environment(new ArrayLoader([
            'menu' => '<a href="{{ fragment.url }}">{{ fragment.title }}</a>',
        ]));

        $html = $twig->render('menu', [
            'fragment' => new Fragment(['url' => 'https://example.com', 'title' => 'Tools']),
        ]);

        $this->assertSame('<a href="https://example.com">Tools</a>', $html);
    }
}
