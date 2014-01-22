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
session_start();
include 'bootstrap.php';

use Symfony\Component\Translation\Translator;

$_SESSION['install']['lang'] = "en_US";

if($_REQUEST['lang']){
	$_SESSION['install']['lang'] = $_REQUEST['lang'];
}

$trans = new Translator();
$trans->setLocale($_SESSION['install']['lang']);
$trans->addLoader("php",  new Symfony\Component\Translation\Loader\PhpFileLoader());
$trans->addResource('php', __DIR__.'/I18n/'.$_SESSION['install']['lang'].'.php', $_SESSION['install']['lang']);


?>
<!DOCTYPE html>
<html lang="">
<head>
    <title>Installation</title>
    <link rel="shortcut icon" href="fd33fd0-6fda040.ico" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="topbar">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <div class="version-info"><?php echo $trans->trans('Version undefined'); ?></div>
            </div>
        </div>
    </div>
</div>
<div class="install">
    <div id="wrapper" class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="general-block-decorator">
                    <h3 class="title title-without-tabs"><?php echo $trans->trans('Thelia installation wizard'); ?></h3>
                    <div class="wizard">
                        <ul>
                            <li class="<?php if($step == 1){ echo 'active'; } elseif ($step > 1) { echo 'complete'; }?>"><span class="badge">1</span><?php echo $trans->trans('Welcome'); ?><span class="chevron"></span></li>
                            <li class="<?php if($step == 2){ echo 'active'; } elseif ($step > 2) { echo 'complete'; }?>"><span class="badge">2</span><?php echo $trans->trans('Checking permissions'); ?><span class="chevron"></span></li>
                            <li class="<?php if($step == 3){ echo 'active'; } elseif ($step > 3) { echo 'complete'; }?>"><span class="badge">3</span><?php echo $trans->trans('Database connection'); ?><span class="chevron"></span></li>
                            <li class="<?php if($step == 4){ echo 'active'; } elseif ($step > 4) { echo 'complete'; }?>"><span class="badge">4</span><?php echo $trans->trans('Database selection'); ?><span class="chevron"></span></li>
                            <li class="<?php if($step == 5){ echo 'active'; } elseif ($step > 5) { echo 'complete'; }?>"><span class="badge">5</span><?php echo $trans->trans('General information'); ?><span class="chevron"></span></li>
                            <li class="<?php if($step == 6){ echo 'active'; } elseif ($step > 6) { echo 'complete'; }?>"><span class="badge">6</span><?php echo $trans->trans('Thanks'); ?><span class="chevron"></span></li>
                        </ul>
                    </div>
