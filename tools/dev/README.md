# Running the site and dashboard locally

Everything runs against a throwaway database, and a local publish writes to a
scratch folder, so nothing here can touch the live site or the repo's
`index.html`.

1. **Database** — any MySQL or MariaDB. With MariaDB from scoop:

   ```bash
   mariadb-install-db --datadir=<scratch>/data --port=3317
   mariadbd --defaults-file=<scratch>/data/my.ini --console
   mysql -uroot --port=3317 -h127.0.0.1 -e "CREATE DATABASE sbx; CREATE USER 'sbx'@'%' IDENTIFIED BY 'sbxlocal'; GRANT ALL ON sbx.* TO 'sbx'@'%';"
   ```

2. **Config** — `admin/inc/config.php` loads `admin/inc/config.local.php` when
   it exists (gitignored, never deployed). Copy `config.example.php`, point the
   database at the one above (`DB_HOST = '127.0.0.1;port=3317'`), set
   `SITE_URL` to `http://127.0.0.1:8412/`, and send `PUBLISHED_FILE` and
   `UPLOAD_DIR` somewhere scratch (`sys_get_temp_dir() . '/sbx-dev'`).

3. **Seed** — `php tools/dev/seed.php`. Creates the tables, a local account
   (`dev@smashbox.test` / `local-dev-pass`), a few leads and three months of
   made-up visits.

4. **Serve**

   ```bash
   php -S 127.0.0.1:8412 tools/dev/site-router.php     # the public site
   cd admin && php -S 127.0.0.1:8413                   # the dashboard
   ```
