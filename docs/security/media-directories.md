# No PHP under the media directories

Thelia publishes the images and documents it serves under `public/cache/` (`image_cache_dir_from_web_root` and `document_cache_dir_from_web_root` move them). The core already refuses to store a file whose name carries a server-executable extension or whose content is a PHP script, and it never publishes in `public/cache/` an image source that is not an image. Telling the web server never to run PHP there is a second line of defence against a file that got through some other way, a module writing to the directory itself for instance.

It is optional: nothing in the core depends on it, and a shop that keeps `local/media/` outside the document root, as the default layout does, only needs it for `public/cache/`.

## Apache

Write `public/cache/.htaccess`. It applies to every subdirectory, the document cache included, whose own `.htaccess` keeps applying on top of it.

```apache
# No script runs from the media cache, under mod_php or PHP-FPM.
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<FilesMatch "\.(?i:ph(p[3-8st]?|t(ml?)?|ar)|s(html?|tm))$">
    Require all denied
</FilesMatch>
```

`php_flag` needs `AllowOverride Options` (or `All`) and `Require` needs `AllowOverride AuthConfig`. Without them Apache answers 500 for the whole directory: check an image URL once the file is in place.

## nginx

Add this block to the server block of the shop, before the location that passes `.php` files to PHP-FPM. With the `^~` prefix, nginx never looks at the regular expression locations for a path under `/cache/`, so no file there reaches PHP-FPM; the nested location answers 403 for the names a server could run.

```nginx
location ^~ /cache/ {
    location ~* \.(?:ph(?:p[3-8st]?|t(?:ml?)?|ar)|s(?:html?|tm))$ {
        deny all;
    }
}
```

A shop that added the `location ^~ /cache/documents/` block of the 3.2.1 upgrade notes keeps it: nginx picks the longest prefix, and that block does not pass anything to PHP-FPM either.
