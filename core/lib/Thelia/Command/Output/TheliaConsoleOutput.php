<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
        $ouput = array();
        foreach ($messages as $message) {
            $output[] = "<" . $style . ">" . "  " . $message . str_repeat(' ', $length - $strlen($message)) . "  </" . $style . ">";
        }

        $this->writeln($output);
    }

}
