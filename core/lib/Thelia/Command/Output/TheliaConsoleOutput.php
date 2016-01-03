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

namespace Thelia\Command\Output;

use Symfony\Component\Console\Output\ConsoleOutput;

class TheliaConsoleOutput extends ConsoleOutput
{
    public function renderBlock(array $messages, $style = "info")
    {
        $strlen = function ($string) {
            if (!function_exists('mb_strlen')) {
                return strlen($string);
            }

            if (false === $encoding = mb_detect_encoding($string)) {
                return strlen($string);
            }

            return mb_strlen($string, $encoding);
        };
        $length = 0;
        foreach ($messages as $message) {
            $length = ($strlen($message) > $length) ? $strlen($message) : $length;
        }
        $output = array();
        foreach ($messages as $message) {
            $output[] = "<" . $style . ">" . "  " . $message . str_repeat(' ', $length - $strlen($message)) . "  </" . $style . ">";
        }

        $this->writeln($output);
    }
}
