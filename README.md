# yii2-sql-migrations
Migration controller that works with sql-files `...up.sql` and `...down.sql`.


## Installation

```bash
composer require gozoro/yii2-sql-migrations
```

## Configuration

Edit you `config/console.php`

```php
...
$config = [
	...
	'components' => [
		...
	],

	'controllerMap' => [
		'migrate' => [
			'class' => 'gozoro\sql_migrations\MigrateController',
			//'migrationTable' => 'other_migration_table',
			//'migrationPath' => '@app/other_migration_path',
			//'db' => 'other_database_name'
	],
	...
];

return $config

```

## Usage

Сreate your migration files in the directory that is specified in `migrationPath`.

File naming example:

- 01_create_table.up.sql
- 01_create_table.down.sql
- 02_insert_data.up.sql
- 02_insert_data.down.sql

Run migration:

```bash
./yii migrate/up
```


## Commands

```bash

./yii help migrate
```
```
DESCRIPTION

Migration tool using sql-files ...up.sql and ...down.sql

File naming example:

- 01_create_table.up.sql
- 01_create_table.down.sql
- 02_insert_data.up.sql
- 02_insert_data.down.sql

To sort files well, you can add as many zeros to the beginning of the file name as needed.


SUB-COMMANDS

- migrate/down          Downgrades the application by reverting old migrations.
- migrate/history       Displays the migration history.
- migrate/new           Displays the un-applied new migrations.
- migrate/redo          Redoes the last few migrations.
- migrate/to            Upgrades or downgrades till the specified version.
- migrate/up (default)  Upgrades the application by applying new migrations.

To see the detailed information about individual sub-commands, enter:

  yii help <sub-command>
```


