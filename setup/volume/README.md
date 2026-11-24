# Measuring the shop with a large catalogue

Three console commands give a reproducible measure of how Thelia holds when the catalogue and the
order history grow. They are test tools: they write hundreds of thousands of rows and are meant for
a disposable shop, never a live one.

| Command | What it does |
|---|---|
| `thelia:test:volume:generate` | Writes a category tree, products with combinations, features, images, translations and prices, customers with an address, and orders. Refuses `APP_ENV=prod`, and a database holding orders it did not write unless `--force`. |
| `thelia:test:volume:measure` | Calls each page of [`pages.yaml`](pages.yaml) on the running shop and counts the queries its database receives, saves a product sheet of the back office as it stands, then times the long jobs (exports). |
| `thelia:test:volume:purge` | Deletes the dataset, and only it. `--dry-run` counts first. |

## What is measured

The main measure is the **number of database queries per page**. It does not depend on the machine
or the load, and it is what predicts how a page holds as the catalogue grows: a page whose count
stays flat holds, a page whose count grows with the number of products, orders or customers does not.
Times (time to first byte, total) depend on the machine and are given as an order of magnitude.

A page of the plan can also measure the save of its form (`submit`): the page is read to fill the
form and is not counted, then the post is counted. The plan saves the newest generated product
from its back-office sheet, unchanged, and the save must redirect to the product list, which is how
the back office tells a saved sheet from a refused one. Each save changes the update date and the
version of that product, so the fingerprint of the dataset differs after a campaign; the query
counts do not.

The count is read from the database server itself (`SHOW GLOBAL STATUS LIKE 'Questions'`) before
and after each call, so it is exact only while nothing else talks to the database server. After a
call the counter is read again until it only moves by its own readings: behind PHP-FPM, the shop
sends the response before the end of the request and may still query the database afterwards. Each page
is called once to warm up, then `--runs` times. Its count is the fewest queries over those calls: a
cache that expires during the measure (one kept for a minute adds a few queries once a minute) costs
one call more, not the steady state of the page. Every call is kept in the JSON file.

## The dataset

The same options on the same starting database write the same dataset, down to its identifiers and
dates: every random choice comes from one generator seeded by `--seed`, every date is counted back
from `--base-date`. The command prints a fingerprint of the rows it wrote; two generations with the
same fingerprint hold the same data.

- Products are spread over the leaves of the category tree unevenly, as in a real catalogue: a few
  categories hold many products. One product in five is also in a second category.
- A product has one combination, or two to four sizes in a colour; a price in every currency; one to
  three images; a material, and often an origin and a warranty.
- Images reuse `--images` source files (eight by default) shared by every product: a hundred thousand
  copies would weigh gigabytes. The image cache is therefore warmer than a real shop's.
- Customers have one address on a domain reserved for examples (`example.com`) and the password
  `volume-dataset`. Orders are spread over the year before the base date, one to four lines each;
  a few customers place many orders.
- Every generated product, combination, customer and order reference starts with `VOL-`, and a
  `meta_data` row (`meta_key = volume_dataset`) records the identifier ranges the dataset wrote. The
  purge deletes those ranges and nothing else.

## Protocol

Run each step in the web container (`ddev ssh`, or prefix with `ddev exec`).

1. **Start from a fresh install with the demo**, the theme modules only (no module of
   `local/modules` activated: a module can add queries to every page):

   ```bash
   php bin/install --with-demo --with-admin
   ```

2. **Generate the dataset** in the development environment. The demo orders are not real ones,
   hence `--force`:

   ```bash
   APP_ENV=dev php bin/console thelia:test:volume:generate --products=10000 --orders=10000 --customers=5000 --force
   ```

   Keep the printed fingerprint and generation times: they belong to the report.

3. **Serve the shop in the production environment** and warm its cache, so that the measure does
   not count the compilation of the container:

   ```bash
   # DDEV: ddev config --web-environment-add=APP_ENV=prod && ddev restart
   rm -rf var/cache/prod && APP_ENV=prod php bin/console cache:warmup
   ```

4. **Measure twice** on the same dataset, then compare:

   ```bash
   php bin/console thelia:test:volume:measure --base-url=https://my-shop.ddev.site --runs=3 --output=var/volume-1.json
   php bin/console thelia:test:volume:measure --base-url=https://my-shop.ddev.site --runs=3 --skip-jobs --compare=var/volume-1.json
   ```

   The second run fails if any page gives another count. Note the machine, the database server and
   its version (in the JSON file), and whether the shared cache of API resources is on.

5. **Walk the shop by hand** at each size: a category, a product, the cart, the back-office lists.
   The suites do not see everything a person does.

6. **Purge**, and check the database is back to where it was:

   ```bash
   APP_ENV=dev php bin/console thelia:test:volume:purge --dry-run
   APP_ENV=dev php bin/console thelia:test:volume:purge
   ```

## Reading the results

Compare the counts of a page across sizes. A count that stays the same holds. A count that grows
with the catalogue, the orders or the customers is a break point: it gets a ticket with its page,
its counts at each size and the queries that repeat (MariaDB's general log, grouped by statement,
shows which ones). Before calling a slow query a code problem, check it against the indexes of
`local/config/schema.xml`.
