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

namespace Thelia\Tests\Support\Order;

/**
 * A throwaway PDF theme next to the installed ones, made active for a test, so the `preparation` document can be
 * exercised without a theme that carries it. The template prints one block per order id, with a page break
 * before each but the first, the locale the service passes (`picking_slip_locale`), and the barcode of the order id.
 */
trait PickingSlipFixtureTheme
{
    // A name of its own for each test: the compiled Twig templates are cached by path and not rebuilt in test.
    private ?string $fixtureTheme = null;

    private const string ENV_NAME = 'ACTIVE_PDF_TEMPLATE';

    private ?string $previousPdfTheme = null;

    private function installPickingSlipTheme(bool $withPreparationTemplate = true): void
    {
        $this->fixtureTheme = 'zz-picking-slip-'.uniqid();
        $directory = THELIA_TEMPLATE_DIR.'pdf/'.$this->fixtureTheme;
        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        copy(THELIA_TEMPLATE_DIR.'pdf/default/template.xml', $directory.'/template.xml');

        if ($withPreparationTemplate) {
            file_put_contents($directory.'/preparation.html.twig', <<<'TWIG'
                <html><body>
                {% for order_id in order_ids %}
                <div{% if not loop.first %} style="page-break-before: always"{% endif %}>
                <p>SLIP-{{ order_id }} locale={{ picking_slip_locale }}</p>
                <img src="{{ barcode_code128('REF' ~ order_id) }}" alt="REF{{ order_id }}">
                </div>
                {% endfor %}
                </body></html>
                TWIG);
        }

        // The test environment pins the active PDF theme in the environment, and a variable wins over the
        // stored configuration: it is the one to move.
        $this->previousPdfTheme = $_ENV[self::ENV_NAME] ?? null;
        $_ENV[self::ENV_NAME] = $this->fixtureTheme;
    }

    private function removePickingSlipTheme(): void
    {
        if (null === $this->fixtureTheme) {
            return;
        }

        if (null !== $this->previousPdfTheme) {
            $_ENV[self::ENV_NAME] = $this->previousPdfTheme;
        } else {
            unset($_ENV[self::ENV_NAME]);
        }

        $directory = THELIA_TEMPLATE_DIR.'pdf/'.$this->fixtureTheme;
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }

    private function pageCount(string $pdf): int
    {
        return (int) preg_match_all('#/Type\s*/Page\b(?!s)#', $pdf);
    }
}
