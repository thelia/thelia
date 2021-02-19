<!doctype html>

{* Declare assets directory, relative to template base directory *}
{declare_assets directory='assets/dist'}
{* Set the default translation domain, that will be used by {intl} when the 'd' parameter is not set *}
{default_translation_domain domain='fo.starter'}

{* -- Define some stuff for Smarty ------------------------------------------ *}
{config_load file='variables.conf'}
{block name="init"}{/block}
{block name="no-return-functions"}{/block}
{assign var="store_name" value={config key="store_name"}}
{assign var="store_description" value={config key="store_description"}}
{assign var="lang_code" value={lang attr="code"}}
{assign var="lang_locale" value={lang attr="locale"}}
{if not $store_name}{assign var="store_name" value={intl l='Thelia V2'}}{/if}
{if not $store_description}{assign var="store_description" value={$store_name}}{/if}

{loop type="auth" name="isConnected.check" role="CUSTOMER"}
  {assign var="isConnected" value=true}
{/loop}

<html lang="{$lang_locale|replace:'_':'-'}" class="no-js">
<head>
  <meta charset="utf-8">

    {store_seo_meta locale=$lang_locale}

    {* Page Title *}
    <title>{block name="page-title"}{strip}{if $page_title}{$page_title}{elseif $breadcrumbs}{foreach from=$breadcrumbs|array_reverse item=breadcrumb}{$breadcrumb.title|unescape} - {/foreach}{$store_name}{else}{$store_name}{/if}{/strip}{/block}</title>


    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>

    {* Meta Tags *}
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="generator" content="{intl l='Thelia V2'}">
    <meta name="format-detection" content="telephone=no">

    <meta property="og:url" content="{navigate to='current'}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{block name="page-title"}{strip}{if $page_title}{$page_title}{elseif $breadcrumbs}{foreach from=$breadcrumbs|array_reverse item=breadcrumb}{$breadcrumb.title|unescape} - {/foreach}{$store_name}{else}{$store_name}{/if}{/strip}{/block}">
    <meta property="og:description" content="{if $page_description}{$page_description}{else}{$store_description}{/if}">
    <meta property="og:site_name" content="{$store_name}">
    <meta property="og:locale" content="{lang attr="locale"}">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:url" content="{navigate to='current'}">
    <meta name="twitter:title" content="{block name="page-title"}{strip}{if $page_title}{$page_title}{elseif $breadcrumbs}{foreach from=$breadcrumbs|array_reverse item=breadcrumb}{$breadcrumb.title|unescape} - {/foreach}{$store_name}{else}{$store_name}{/if}{/strip}{/block}">
    <meta name="twitter:description" content="{if $page_description}{$page_description}{else}{$store_description}{/if}">

    {block name="meta"}
        <meta name="description" content="{if $page_description}{$page_description}{else}{$store_description}{/if}">

        {if $page_keywords}
          <meta name="keywords" content="{$page_keywords}">
        {else}
          <meta name="keywords" content="{$default_keywords}">
        {/if}

        {* Share meta *}
        <meta property="og:image" content="{getFileFromManifest file="default-social-thumbnail.png"}" />
        <meta property="og:image:secure_url" content="{getFileFromManifest file="default-social-thumbnail.png"}" />
        <meta property="og:image:width" content="450" />
        <meta property="og:image:height" content="450" />
        <meta name="twitter:image" content="{getFileFromManifest file="default-social-thumbnail.png"}" />
    {/block}

    {* CSS *}
    {block name="css"}
      {getAssetsFromEntrypoints entry="app" type="css"}
    {/block}

    {* FAVICON *}
    <link rel="apple-touch-icon" sizes="180x180" href="{getFileFromManifest file="dist/favicons/apple-touch-icon.png"}">
    <link rel="icon" type="image/png" sizes="32x32" href="{getFileFromManifest file="dist/favicons/favicon-32x32.png"}">
    <link rel="icon" type="image/png" sizes="16x16" href="{getFileFromManifest file="dist/favicons/favicon-16x16.png"}">
    <link rel="manifest" href="{getFileFromManifest file="dist/favicons/site.webmanifest"}">
    <link rel="mask-icon" href="{getFileFromManifest file="dist/favicons/safari-pinned-tab.svg"}" color="#5bbad5">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="theme-color" content="#ffffff">


    {* Favicon *}
    {* <link rel="shortcut icon" type="image/x-icon" href="{getFileFromManifest file="favicon.ico"}"> *}

    {* Feeds *}
    <link rel="alternate" type="application/rss+xml" title="{intl l='All products'}" href="{url path="/feed/catalog/%lang" lang=$lang_locale}" />
    <link rel="alternate" type="application/rss+xml" title="{intl l='All contents'}" href="{url path="/feed/content/%lang" lang=$lang_locale}" />
    <link rel="alternate" type="application/rss+xml" title="{intl l='All brands'}"   href="{url path="/feed/brand/%lang" lang=$lang_locale}" />
    {block name="feeds"}{/block}

    {hook name="main.head-bottom"}
</head>

<body class="text-gray-700 bg-gray-50 {block name="body-class"}{/block}" itemscope itemtype="http://schema.org/WebPage">
    {include file="microdata/store.html"}

    {block name="header"}
        {include file="components/smarty/Header/Header.html"}
    {/block}

    {block name="navigation"}
      {include file="components/smarty/Navigation/Navigation.html"}
    {/block}

    <div class="Layout">

      <main class="relative z-0 px-8" role="main">
        {hook name="main.content-top"}
        {block name="main-content"}{/block}
        {hook name="main.content-bottom"}
      </main><!-- /.main-container -->
    </div>

    {block name="footer"}
      {include file="components/smarty/Footer/Footer.html"}
    {/block}

    {block name="javascript-data"}{/block}

    {include file="components/smarty/CookieBar/CookieBar.html"}

    {block name="minicart"}{include file="components/React/MiniCart/MiniCart.html" }{/block}
    {block name="minilogin"}{include file="components/React/MiniLogin/MiniLogin.html"}{/block}
    {block name="search"}{include file="components/smarty/FullSearch/FullSearch.html"}{/block}

    {block name="modals"}{/block}

    <script>
       var DEFAULT_CURRENCY_CODE = "{currency attr="code"}"
       var DEFAULT_CURRENCY_SYMBOL = "{currency attr="symbol"}"
       var SVG_SPRITE_URL = "{getFileFromManifest file="dist/sprite.svg"}"
    </script>


    {block name="javascript"}
      {getAssetsFromEntrypoints entry="app" type="js"}
    {/block}

</body>
</html>
