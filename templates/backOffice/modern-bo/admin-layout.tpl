{block name="check-auth"} {check_auth role="ADMIN" resource="{block
name="check-resource"}{/block}" module="{block name="check-module"}{/block}"
access="{block name="check-access"}{/block}" login_tpl="/admin/login"} {/block}
{* -- Define some stuff for Smarty ------------------------------------------ *}
{config_load file='variables.conf'} {* Set the default translation domain, that
will be used by {intl} when the 'd' parameter is not set *}
{default_translation_domain domain='bo.modern-bo'} {block
name="no-return-functions"}{/block}

<!DOCTYPE html>
<html lang="{$lang_code}">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>
      {block name="page-title"}Default Page Title{/block} - {intl l='Thelia Back
      Office'}
    </title>

    {block name="css"} {getAssetsFromEntrypoints entry="app" type="css"}
    {/block}

    <script
      src="https://kit.fontawesome.com/a602a5fdd3.js"
      crossorigin="anonymous"
    ></script>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap"
      rel="stylesheet"
    />
  </head>

  <body>
    <div id="page" class="min-h-screen">
      {* display top bar only if admin is connected *} {loop name="top-bar-auth"
      type="auth" role="ADMIN"} {* -- Brand bar section
      ------------------------------------------------- *} {hook
      name="main.before-topbar" location="before_topbar" }

      <header
        id="header"
        class="flex items-center justify-between bg-mediumPearl shadow-xl"
      >
        {include file="components/Header/header.html"}
      </header>

      <aside
        id="sidebar"
        class="flex flex-col bg-mediumCharbon"
        role="navigation"
      >
        {include file="components/MainMenu/main-menu.html"} {hook
        name="main.inside-topbar" location="inside_topbar"}
      </aside>

      {hook name="main.after-topbar" location="after_topbar" }

      <main id="main">
        {* -- Main page content section
        ----------------------------------------- *} {hook
        name="main.before-content" location="before_content"} {block
        name="main-content"}Put here the content of the template{/block} {hook
        name="main.after-content" location="after_content"}
      </main>
      {/loop}
    </div>

    {block name="javascript"} {getAssetsFromEntrypoints entry="app" type="js"}
    {/block}
  </body>
</html>
