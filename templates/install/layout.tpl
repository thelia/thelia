<!DOCTYPE html>
<html lang="{$lang_code}">
<head>
    <title>{block name="page-title"}Thelia Install{/block}</title>

    {images file='../admin/default/assets/img/favicon.ico'}<link rel="shortcut icon" href="{$asset_url}" />{/images}

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {stylesheets file='../admin/default/assets/less/*' filters='less,cssembed'}
        <link rel="stylesheet" href="{$asset_url}">
    {/stylesheets}

</head>
<body>
    <div class="topbar">
        <div class="container">

            <div class="row">
                <div class="col-md-6">
                    <div class="version-info">{intl l='Version %ver' ver="{$THELIA_VERSION}"}</div>
                </div>                
            </div>

        </div>
    </div>

    {* -- Main page content section ----------------------------------------- *}

    {block name="main-content"}Put here the content of the template{/block}

    {* -- Footer section ---------------------------------------------------- *}

    <hr />
    <footer class="footer">
        <div class="container">
            <p>{intl l='&copy; Thelia 2013'}
            - <a href="http://www.openstudio.fr/" target="_blank">{intl l='Édité par OpenStudio'}</a>
            - <a href="http://forum.thelia.net/" target="_blank">{intl l='Forum Thelia'}</a>
            - <a href="http://contrib.thelia.net/" target="_blank">{intl l='Contributions Thelia'}</a>
            </p>

            {module_include location='in_footer'}

        </div>
    </footer>

    {* -- Javascript section ------------------------------------------------ *}

    <script src="http://code.jquery.com/jquery-2.0.3.min.js"></script>

    {block name="after-javascript-include"}{/block}

    {javascripts file='../admin/default/assets/js/bootstrap/bootstrap.js'}
        <script src="{$asset_url}"></script>
    {/javascripts}

    {block name="javascript-initialization"}{/block}

</body>
</html>