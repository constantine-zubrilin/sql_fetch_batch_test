# Doctrine iterator memory test

Question to answer: how many rows are fetched from DB when iterator is used?

## Tl;DR

Php fetches more than 1 row when Doctrine iterator is used. Although there is a nuance, php does not tell about it.

## Test data

PostgreSQL table:

```sql
CREATE TABLE public."user" (
	id int4 NOT NULL GENERATED BY DEFAULT AS IDENTITY,
	"name" varchar NOT NULL,
	biography text NOT NULL DEFAULT ''::text,
	CONSTRAINT user_pk PRIMARY KEY (id)
);
```

Table has 100 rows with huge text value in `biography`:

```
1	85eb6504b50590ad9de3a3be42ab158c    10MB_OF_TEXT...
2	29c4c4d042bd303ca4f7de75a6acbfcd    10MB_OF_TEXT...
...
```

## Benchmarks

### Select 1 row using limit

[Source](src/Command/BenchmarkSingle.php):

```php
$query = $this->entityManager->createNativeQuery('select * from "user" limit 1', $rsm);
$row = $query->getResult();
```

Result:

```
19:29:53        Before  Mem usage: 12 Mb        Mem peak: 22.34765625 Mb
19:29:53        After   Mem usage: 22.34765625 Mb       Mem peak: 22.34765625 Mb
```

### Select several rows using limit

[Source](src/Command/BenchmarkBatch.php):

```php
$query = $this->entityManager->createNativeQuery('select * from "user" limit 2', $rsm);
$row = $query->getResult();
```

Result:

```
Limit 2:
19:30:34        Before  Mem usage: 12 Mb        Mem peak: 22.34765625 Mb
19:30:35        After   Mem usage: 32.6953125 Mb        Mem peak: 32.6953125 Mb

Limit 3:
19:30:51        Before  Mem usage: 12 Mb        Mem peak: 22.34765625 Mb
19:30:52        After   Mem usage: 43.04296875 Mb       Mem peak: 43.04296875 Mb

Limit 5:
19:31:10        Before  Mem usage: 12 Mb        Mem peak: 22.34765625 Mb
19:31:11        After   Mem usage: 63.73828125 Mb       Mem peak: 63.73828125 Mb
```

### Select 100 rows using iterator

[Source](src/Command/BenchmarkIterator.php):

```php
$query = $this->entityManager->createNativeQuery('select * from "user"', $rsm);
foreach ($query->toIterable() as $row) {
    $this->mem('After ' . $row['id']);
}
```

Result:

```
19:12:29        Before: Mem usage: 12 Mb        Mem peak: 22.34765625 Mb
19:12:55        After 1:        Mem usage: 22.34765625 Mb       Mem peak: 22.34765625 Mb
19:12:55        After 2:        Mem usage: 22.34765625 Mb       Mem peak: 32.6953125 Mb
19:12:55        After 3:        Mem usage: 22.34765625 Mb       Mem peak: 32.6953125 Mb
All other rows have the same result
```

### Results analysis

Fetching 1 row requires ~10Mb because each row has 10b of data. If script fetches more N rows, php consumes ~N * 10Mb
rows. This is according to `memory_get_peak_usage()` and `memory_get_usage()`.

When iteration is used to fetch data, script has a ~20 seconds delay before fetching first row. I checked the memory
used by process and is uses 1 Gb of memory (100 rows * 10 Mb in each row) in peak. Even though `memory_get_peak_usage()`
shows only ~10 Mb used by row.

Indeed, more than one row is fetched from DB, but php does not know about it. Even setting memory limit to 40Mb
(`ini_set('memory_limit', '40M')`) does not trigger "Allowed memory size of N bytes exhausted".

More info:

https://stackoverflow.com/a/38707938

https://www.php.net/manual/en/mysqlinfo.concepts.buffering.php
