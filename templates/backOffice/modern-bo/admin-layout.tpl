{block name="check-auth"}
  {check_auth
    role="ADMIN"
    resource="{block name="check-resource"}{/block}"
    module="{block name="check-module"}{/block}"
    access="{block name="check-access"}{/block}"
    login_tpl="/admin/login"
  }
{/block}

{* -- Define some stuff for Smarty ------------------------------------------ *}
{config_load file='variables.conf'}

{* Set the default translation domain, that will be used by {intl} when the 'd' parameter is not set *}
{default_translation_domain domain='bo.modern-bo'}

{block name="no-return-functions"}{/block}

<!DOCTYPE html>
<html lang="{$lang_code}">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>
      {block name="page-title"}Default Page Title{/block} - {intl l='Thelia Back Office'}
    </title>

    {block name="css"}
        {encore_entry_link_tags entry="app"}
    {/block}

    <script src="https://kit.fontawesome.com/a602a5fdd3.js" crossorigin="anonymous"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  </head>

  <body>
    <div id="page" class="min-h-screen bg-mediumPearl">
      {* display top bar only if admin is connected *}
      {loop name="top-bar-auth" type="auth" role="ADMIN"}
        {* -- Brand bar section ------------------------------------------------- *}
        {hook name="main.before-topbar" location="before_topbar" }

        <header id="header"
          class="sticky top-0 z-20 flex items-center justify-center lg:justify-between bg-white shadow-lg max-h-24 lg:max-h-16 py-3 lg:py-0">
          {include file="components/Header/Header.html"}
        </header>

        <aside id="sidebar" class="z-10 hidden lg:flex flex-col justify-between bg-mediumCharbon" role="navigation">
          {include file="components/SideBar/SideBar.html"}
        </aside>

        {hook name="main.after-topbar" location="after_topbar" }

        <main id="main" class="py-10 px-6 lg:px-20">
          {* -- Main page content section ----------------------------------------- *}
          {hook name="main.before-content" location="before_content"}
          {block name="main-content"}
            Put here the content of the template
          {/block}
          {hook name="main.after-content" location="after_content"}
        </main>

        <footer id="footer" class="flex flex-col lg:flex-row items-center lg:justify-between bg-white shadow-xl py-6 px-4 lg:p-0">
          {include file="components/Footer/Footer.html"}
        </footer>
      {/loop}
    </div>

    {block name="javascript"}
        {encore_entry_script_tags entry="app"}
    {/block}
  </body>
</html>
