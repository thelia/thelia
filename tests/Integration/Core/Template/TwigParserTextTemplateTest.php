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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Test\IntegrationTestCase;
use Twig\Loader\FilesystemLoader;
use TwigEngine\Template\TwigParser;

/**
 * The Twig parser must render both HTML and text templates: a ".html" request resolves to
 * a ".html.twig" file and a ".txt" request to a ".txt.twig" file (the text version of an
 * email). Native {% extends %} layouts must work, and a missing template must raise a
 * ResourceNotFoundException so callers such as Message can fall back gracefully.
 */
final class TwigParserTextTemplateTest extends IntegrationTestCase
{
    private string $templateDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateDir = sys_get_temp_dir().'/twigparser_'.uniqid('', true);
        mkdir($this->templateDir);

        file_put_contents($this->templateDir.'/probe.html.twig', 'HTML {{ 1 + 1 }}');
        file_put_contents($this->templateDir.'/probe.txt.twig', 'TEXT {{ 1 + 1 }}');
        file_put_contents($this->templateDir.'/probe_layout.html.twig', '[{% block body %}{% endblock %}]');
        file_put_contents(
            $this->templateDir.'/probe_child.html.twig',
            "{% extends 'probe_layout.html.twig' %}{% block body %}CHILD{% endblock %}",
        );

        /** @var FilesystemLoader $loader */
        $loader = static::getContainer()->get('twig.loader.native_filesystem');
        $loader->addPath($this->templateDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->templateDir.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->templateDir);

        parent::tearDown();
    }

    public function testRendersHtmlTemplateForAnHtmlRequest(): void
    {
        self::assertSame('HTML 2', $this->parser()->render('probe.html'));
    }

    public function testRendersTextTemplateForATxtRequest(): void
    {
        self::assertSame('TEXT 2', $this->parser()->render('probe.txt'));
    }

    public function testRendersNativeExtendsLayout(): void
    {
        self::assertStringContainsString('[CHILD]', $this->parser()->render('probe_child.html'));
    }

    public function testMissingTemplateRaisesResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->parser()->render('probe_absent.txt');
    }

    private function parser(): TwigParser
    {
        return $this->getService(TwigParser::class);
    }
}
