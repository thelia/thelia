{* -- By default, check admin login ----------------------------------------- *}

{block name="check-auth"}
  {check_auth role="ADMIN" resource="{block name="check-resource"}{/block}" module="{block name="check-module"}{/block}"
  access="{block name="check-access"}{/block}" login_tpl="/admin/login"}
{/block}

{block name="no-return-functions"}{/block}

{* -- Define some stuff for Smarty ------------------------------------------ *}
{config_load file='variables.conf'}

{* Set the default translation domain, that will be used by {intl} when the 'd' parameter is not set *}
{default_translation_domain domain='bo.modern-bo'}

<!DOCTYPE html>
<html lang="{$lang_code}">

<head>
  <meta charset="utf-8">

  <title>{block name="page-title"}Default Page Title{/block} - {intl l='Thelia Back Office'}</title>

  <link rel="shortcut icon" href="{image file='assets/images/favicon.ico'}" />

  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">

  <script src="https://kit.fontawesome.com/a602a5fdd3.js" crossorigin="anonymous"></script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet" />

  {block name="meta"}{/block}


  {block name="css"}
    {getAssetsFromEntrypoints entry="app" type="css"}
  {/block}

  {* Modules css are included here *}

  {hook name="main.head-css" location="head_css" }
</head>

<body class="SimpleLayout">

  <div class="flex items-center justify-center min-h-screen">
    {* -- Main page content section ----------------------------------------- *}
    {hook name="main.before-content" location="before_content"}

    {block name="main-content"}Put here the content of the template{/block}

    {hook name="main.after-content" location="after_content"}
  </div>

  {hook name="main.before-footer" location="before_footer" }

  <footer class="flex flex-col items-center justify-between p-4 mt-4 text-sm shadow-xl md:flex-row bg-mediumPearl md:text-base">
    {include file="components/Footer/Footer.html"}

    {hook name="main.in-footer" location="in_footer" }
  </footer>

  {hook name="main.after-footer" location="after_footer" }

  {block name="javascript"}
    {getAssetsFromEntrypoints entry="app" type="js"}
  {/block}
</body>

</html>