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

class TlogDestinationJavascriptConsole extends AbstractTlogDestination
{
    public function getTitle(): string
    {
        return "Browser's Javascript console";
    }

    public function getDescription(): string
    {
        return "The Thelia logs are displayed in your browser's Javascript console.";
    }

    public function write(&$res): void
    {
        $content = '<script>try {'."\n";

        foreach ($this->logs as $line) {
            $content .= "console.log('".str_replace("'", "\\'", str_replace(["\r\n", "\r", "\n"], '\\n', $line))."');\n";
        }

        $content .= '} catch (ex) { alert("Les logs Thelia ne peuvent être affichés dans la console javascript:" + ex); }</script>'."\n";

        if (preg_match('|</body>|i', (string) $res)) {
            $res = preg_replace('|</body>|i', $content . '</html>', (string) $res);
        }
    }
}
