# Updating Postal Code Data

Postal code data comes from [GeoNames](https://www.geonames.org/) and is stored in the `postal_codes` table.

## Source

Download country files from:

**https://download.geonames.org/export/zip/**

Each file is named by ISO country code (e.g. `US.zip`, `CA.zip`). Download and unzip into `support/geonames/`. The US file unpacks to `US.txt`.

## Loading Data

Run the loader script from the project root:

```bash
# Load US data into development DB (default)
php support/geonames/load_postal_codes.php

# Load US data into production DB
php support/geonames/load_postal_codes.php --env production

# Load a different country
php support/geonames/load_postal_codes.php --file support/geonames/CA.txt

# Explicit options
php support/geonames/load_postal_codes.php --env development --file support/geonames/US.txt --country US
```

Re-running is safe — the script deletes existing rows for the target country before inserting, so other countries are unaffected.

## Running the Migration (first time only)

If the `postal_codes` table doesn't exist yet:

```bash
vendor/bin/phinx migrate -e development
vendor/bin/phinx migrate -e production
```

## Verifying the Load

```sql
SELECT country_code, COUNT(*) as rows FROM postal_codes GROUP BY country_code;
SELECT * FROM postal_codes WHERE postal_code = '90210' LIMIT 5;
```
