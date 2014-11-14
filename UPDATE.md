# How to update your Thelia ?

If you have already installed Thelia but a new version is available, you can update easily.

Before proceeding to the update, it's strongly recommended to backup your website (files and database).
You can backup your database with tools such as [phpmyadmin](http://www.phpmyadmin.net)
 or [mysqldump](dev.mysql.com/doc/refman/5.6/en/mysqldump.html).

## 1. Update files

- Download the latest version of Thelia : <http://thelia.net/download/thelia.zip>
- Extract the zip in a temporary directory  
- Then you should replace (not only copy) all the files from the new Thelia version :
   - all files from root directory
   - bin (*optional*)
   - core (**mandatory**)
   - setup (**mandatory**)
- Then, you have to merge (copy in your existent directories) these other directories. Normally, 
    you haven't modify files inside these directories (just created new ones - like your frontOffice template). 
    But If you have modified files, you should proceed carefully and try to report all your changes.      
   - local/config
   - local/modules
   - templates
   - web


## 2. Update database

Then you have 2 different ways to proceed. In each method, a backup of your database can be automatically 
performed if you want to. If an error is encountered, then your database will be restored.   
But if your database is quite large, it's better to make a backup manually. 

### 2.1. use the update script

In a command shell, go to the root directory of your installation, run and follow instructions : 

```bash
php setup/update.php
```

### 2.2. use the update wizard

An update wizard is available in the ```web/install``` directory. It's the same directory used by the install wizard.

**You have to protect the web folder if your site is public (htaccess,  List of allowed IP, ...).**

The update wizard in accessible with your favorite browser :

```bash
http://yourdomain.tld/[/subdomain_if_needed]/install
```

Note:
 
- the wizard is available only if your Thelia is not already in the latest version.
- at the end of the process, the install directory will be removed.


## 3. Clear cache

Once the update is done successfully, you have to clear all caches :  

- clear all caches in all environment :
    - ```php Thelia cache:clear```
    - ```php Thelia cache:clear --env=prod```
    
If the command fails, you can do it manually. Just delete the content of 
the ```cache``` and ```web/cache``` directories. 