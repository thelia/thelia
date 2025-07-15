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

namespace Thelia\Log\Destination;

use Thelia\Log\AbstractTlogDestination;
use Thelia\Log\TlogDestinationConfig;

class TlogDestinationPopup extends AbstractTlogDestination
{
    // Nom des variables de configuration
    // ----------------------------------
    public const VAR_POPUP_WIDTH = 'tlog_destinationpopup_width';

    public const VALEUR_POPUP_WIDTH_DEFAUT = '600';

    public const VAR_POPUP_HEIGHT = 'tlog_destinationpopup_height';

    public const VALEUR_POPUP_HEIGHT_DEFAUT = '600';

    public const VAR_POPUP_TPL = 'tlog_destinationpopup_template';

    // Ce fichier doit se trouver dans le même répertoire que TlogDestinationPopup.class.php
    public const VALEUR_POPUP_TPL_DEFAUT = 'TlogDestinationPopup.tpl';

    public function getTitle(): string
    {
        return 'Javascript popup window';
    }

    public function getDescription(): string
    {
        return 'Display logs in a popup window, separate from the main window .';
    }

    public function getConfigs(): array
    {
        return [
            new TlogDestinationConfig(
                self::VAR_POPUP_TPL,
                'Popup windows template',
                'Put #LOGTEXT in the template text where you want to display logs..',
                file_get_contents(__DIR__ . DS . self::VALEUR_POPUP_TPL_DEFAUT),
                TlogDestinationConfig::TYPE_TEXTAREA,
            ),
            new TlogDestinationConfig(
                self::VAR_POPUP_HEIGHT,
                'Height of the popup window',
                'In pixels',
                self::VALEUR_POPUP_HEIGHT_DEFAUT,
                TlogDestinationConfig::TYPE_TEXTFIELD,
            ),
            new TlogDestinationConfig(
                self::VAR_POPUP_WIDTH,
                'Width of the popup window',
                'In pixels',
                self::VALEUR_POPUP_WIDTH_DEFAUT,
                TlogDestinationConfig::TYPE_TEXTFIELD,
            ),
        ];
    }

    public function write(&$res): void
    {
        $content = '';
        $count = 1;

        foreach ($this->logs as $line) {
            $content .= '<div class="' . (0 !== $count++ % 2 ? 'paire' : 'impaire') . '">' . htmlspecialchars((string) $line) . '</div>';
        }

        $tpl = $this->getConfig(self::VAR_POPUP_TPL);

        $tpl = str_replace('#LOGTEXT', $content, $tpl);
        $tpl = str_replace(["\r\n", "\r", "\n"], '\\n', $tpl);

        $wop = \sprintf(
            '<script>
                _thelia_console = window.open("","thelia_console","width=%s,height=%s,resizable,scrollbars=yes");
                if (_thelia_console == null) {
                   alert("The log popup window could not be opened. Please disable your popup blocker for this site.");
                } else {
                    _thelia_console.document.write("%s");
                    _thelia_console.document.close();
                }
            </script>',
            $this->getConfig(self::VAR_POPUP_WIDTH),
            $this->getConfig(self::VAR_POPUP_HEIGHT),
            str_replace('"', '\\"', $tpl),
        );

        if (preg_match('|</body>|i', (string) $res)) {
            $res = preg_replace('|</body>|i', $wop . '
</body>', (string) $res);
        } else {
            $res .= $wop;
        }
    }
}
