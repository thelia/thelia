#How to update your Thelia

- clear all caches running ```php Thelia cache:clear```
- copy all files from the thelia new version (local/modules/* files too)
- run ```php Thelia thelia:update```
- again clear all caches in all environment :
    - ```php Thelia cache:clear```
    - ```php Thelia cache:clear --env=prod```