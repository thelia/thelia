<?php

ini_set('display_errors', '1');

set_time_limit(0);
ob_start();
session_start();

include 'bootstrap.php';

use Symfony\Component\Translation\Translator;

$lang = isset($_REQUEST['lang']) ? $_REQUEST['lang'] : null;

if($lang){
    $_SESSION['install']['lang'] = $_REQUEST['lang'];
}
elseif(!$lang && !isset($_SESSION['install']['lang'])){
    $_SESSION['install']['lang'] = "en_US";
}

$trans = new Translator($_SESSION['install']['lang']);
$trans->addLoader("php",  new Symfony\Component\Translation\Loader\PhpFileLoader());
$trans->addResource('php', __DIR__.'/I18n/'.$_SESSION['install']['lang'].'.php', $_SESSION['install']['lang']);

if (!isset($context)) {
    $context = 'install';
}

// Check if we store is already configured and if we have to switch on an update process
if ($context == "install" && $step == 1) {
    try {
        $checkPermission = new \Thelia\Install\CheckPermission(true, $trans);
        $isValid = $checkPermission->exec();
        $validationMessage = $checkPermission->getValidationMessages();
    } catch (\Thelia\Install\Exception\AlreadyInstallException $ex) {
        $update = new \Thelia\Install\Update(false);
        if (!$update->isLatestVersion()) {
            $updateLocation = str_replace('/index.php', '', $_SERVER["REQUEST_URI"]) . '/update.php';
            header("Location: " . $updateLocation);
            die();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="">
<head>
    <title><?php
        if ($context == "install") {
            echo $trans->trans('Installation');
        } else {
            echo $trans->trans('Update');
        }
    ?></title>
    <link rel="shortcut icon" href="../favicon.ico" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <style>
        <?php
        // because the installation folder is deleted after the update
        echo file_get_contents('styles.css');
        ?>
    </style>
</head>
<body>

<div class="topbar">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <div class="version-info">
                    <?php echo $trans->trans('Version') . " " . \Thelia\Core\Thelia::THELIA_VERSION ; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php

// Installation

if ($context == "install") { ?>
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

<?php

// Update
} else { ?>
<div class="update">
    <div id="wrapper" class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="general-block-decorator">
                    <h3 class="title title-without-tabs"><?php echo $trans->trans('Thelia installation wizard'); ?></h3>
                    <div class="wizard">
                        <ul>
                            <li class="<?php if($step == 1){ echo 'active'; } elseif ($step > 1) { echo 'complete'; }?>"><span class="badge">1</span><?php echo $trans->trans('Welcome'); ?><span class="chevron"></span></li>
                            <li class="<?php if($step == 2){ echo 'active'; } elseif ($step > 2) { echo 'complete'; }?>"><span class="badge">2</span><?php echo $trans->trans('Update'); ?><span class="chevron"></span></li>
                        </ul>
                    </div>
<?php
}
