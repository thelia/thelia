{* -- By default, check admin login ----------------------------------------- *}

{block name="check-auth"}
    {check_auth role="ADMIN" resource="{block name="check-resource"}{/block}" module="{block name="check-module"}{/block}" access="{block name="check-access"}{/block}" login_tpl="/admin/login"}
{/block}

{block name="no-return-functions"}{/block}

{* -- Define some stuff for Smarty ------------------------------------------ *}
{config_load file='variables.conf'}

{* -- Declare assets directory, relative to template base directory --------- *}
{declare_assets directory='assets'}

{* Set the default translation domain, that will be used by {intl} when the 'd' parameter is not set *}
{default_translation_domain domain='bo.default'}

<!DOCTYPE html>
<html lang="{$lang_code}">
<head>
    <meta charset="utf-8">

    <title>{block name="page-title"}Default Page Title{/block} - {intl l='Thelia Back Office'}</title>

    <link rel="shortcut icon" href="{image file='assets/img/favicon.ico'}" />

    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">

    {block name="meta"}{/block}

    {* -- Bootstrap CSS section --------------------------------------------- *}

    {block name="before-bootstrap-css"}{/block}

    <link rel="stylesheet" href="{stylesheet file='assets/css/styles.css'}">

    {block name="after-bootstrap-css"}{/block}

    {* -- Admin CSS section ------------------------------------------------- *}

    {block name="before-admin-css"}{/block}

    {block name="after-admin-css"}{/block}

    {* Modules css are included here *}

    {hook name="main.head-css" location="head_css" }

    {* HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries *}
    <!--[if lt IE 9]>
    <script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
    {javascripts file='assets/js/libs/respond.min.js'}
    <script src="{$asset_url}"></script>
    {/javascripts}
    <![endif]-->
</head>

<body>
            
    <div class="container">
        {block name="main-content"}{/block}
    </div>

    {hook name="main.before-footer" location="before_footer" }

    <footer class="footer">
        <div class="container">
            <p>{intl l='&copy; Thelia 2013'}
            - <a href="http://www.openstudio.fr/" target="_blank">{intl l='Published by OpenStudio'}</a>
            - <a href="http://thelia.net/forum" target="_blank">{intl l='Thelia support forum'}</a>
            - <a href="http://thelia.net/modules" target="_blank">{intl l='Thelia contributions'}</a>
            </p>

            {hook name="main.in-footer" location="in_footer" }

        </div>
    </footer>

    {hook name="main.after-footer" location="after_footer" }
    

	{* -- Javascript section ------------------------------------------------ *}

	{block name="before-javascript-include"}{/block}
    <script src="//code.jquery.com/jquery-2.0.3.min.js"></script>
    <script>
        if (typeof jQuery == 'undefined') {
            {javascripts file='assets/js/libs/jquery.js'}
            document.write(unescape("%3Cscript src='{$asset_url}' %3E%3C/script%3E"));
            {/javascripts}
        }
    </script>

	{block name="after-javascript-include"}{/block}

    {block name="javascript-initialization"}{/block}

	{* Modules scripts are included now *}
    {hook name='main.footer-js' location="footer_js"}

    {block name="javascript-last-call"}{/block}
    </body>
</html>
