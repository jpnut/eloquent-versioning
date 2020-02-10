# Versions for Eloquent models

[![Latest Version](https://img.shields.io/github/v/release/jpnut/eloquent-versioning.svg?style=flat-square)](https://github.com/jpnut/eloquent-versioning/releases)
[![Quality Score](https://img.shields.io/scrutinizer/quality/g/jpnut/eloquent-versioning.svg?style=flat-square)](https://scrutinizer-ci.com/g/jpnut/eloquent-versioning/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![StyleCI](https://styleci.io/repos/238689246/shield?branch=master)](https://styleci.io/repos/238689246)

This package provides a trait that adds versions to an Eloquent model (forked from [proai/eloquent-versioning](https://github.com/ProAI/eloquent-versioning)).

New versions are created whenever a model is updated. Versions are stored in a separate table for each model (e.g. `user_versions`). 

The package allows for a mixture of versioned and non-versioned attributes.

By default, queries are scoped to merge records in the parent table with the latest version. Additional scopes exist to merge records at a particular version or point in time.

## Installation

This package can be installed through Composer.

```shell script
composer require jpnut/eloquent-versionable
```

## Usage

To add versions to your model you must:
1. Implement the `JPNut\Versioning\Versionable` interface.
2. Use the `JPNut\Versioning\VersionableTrait` trait.
3. Add the `getVersionableOptions` method to your model. This method must return an instance of `JPNut\Versioning\VersionOptions`. You should define which attributes you would like to version by calling `setVersionableAttributes` and passing an array of attributes:

    ```php
    ...
   
    /**
     * @return \JPNut\Versioning\VersionOptions
     */
    public function getVersionableOptions(): VersionOptions
    {
        return VersionOptions::create()
            ->setVersionableAttributes(['email', 'city']);
    }
   
    ...
    ```
4. Add the `version` column to the table of the model which you wish to version. This keeps track of the current version.

    ```php
    ...
   
    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('version')->unsigned()->nullable();
        $table->string('name');
        $table->timestamps();
        $table->softDeletes();
    });
   
    ...
    ```
5. Create a table to contain the versions. This table should contain a reference to the parent model (`parent_id`), the version number `version`, all versionable attributes (`email`, `city`), and the `created_at` timestamp.

    ```php
    ...

    Schema::create('user_versions', function (Blueprint $table) {
        $table->integer('parent_id')->unsigned();
        $table->integer('version')->unsigned();
        $table->string('email');
        $table->string('city');
        $table->timestamp('created_at');

        $table->primary(['parent_id', 'version']);
    });

    ...
    ```


It is assumed that the version table name takes the form `{entity}_versions` where `entity` is the singular form of the entity noun (e.g. `users` and `user_versions`). It is possible to override this and all column names by calling the relevant method on the options object (the following shows the default settings). 
    
```php
...
   
/**
 * @return \JPNut\Versioning\VersionOptions
 */
public function getVersionableOptions(): VersionOptions
{
    return VersionOptions::create()
        ->saveVersionKeyTo('version')
        ->useVersionTable('user_versions')
        ->saveVersionTableKeyTo('version')
        ->versionTableForeignKeyName('parent_id')
        ->versionTableCreatedAtName('created_at');
}

...
```

### Example

```php
use Illuminate\Database\Eloquent\Model;
use JPNut\Versioning\Versionable;
use JPNut\Versioning\VersionableTrait;
use JPNut\Versioning\VersionOptions;

class YourEloquentModel extends Model implements Versionable
{
    use VersionableTrait;
    
    /**
     * @return VersionOptions
     */
    public function getVersionableOptions(): VersionOptions
    {
        return VersionOptions::create()
            ->setVersionableAttributes(['email', 'city']);
    }

    ...
}
```

You can retrieve the model at a specific version by using the `atVersion` scope

```php
Model::atVersion(1)->find(1);
```

You can retrieve the model at a specific point in time by using the `atTime` scope

```php
Model::atTime(now()->subDay())->find(1);
```

Note that this will attempt to find the last version created before the time supplied. If there are no such versions, the method will return `null`.

You can disable the global scope by using the `withoutVersion` scope

```php
Model::withoutVersion()->find(1);
```

You can obtain all versions in the form of a relationship by calling the `versions` property (or method) on a model instance

```php
$model->versions;
```

Note that by default a generic `Version` model is used. You can change this model by overwriting the `versions` method and returning your own `HasMany` relationship. 


You can revert to a previous version by calling the `changeVersion()` method with the desired version as an argument.

```php
$model->changeVersion(1);
```

Note that this creates a new version with the same versionable attribute values as the version specified (rather than changing the value of the `version` column in the parent table).

## Tests

The package contains some integration tests, set up with Orchestra. The tests can be run via phpunit.

```bash
vendor/bin/phpunit
```

## Contributing

Create a Pull Request!

## Alternatives
- [mpociot/versionable](https://github.com/mpociot/versionable)
- [overtrue/laravel-versionable](https://github.com/overtrue/laravel-versionable)
- [proai/eloquent-versioning](https://github.com/ProAI/eloquent-versioning)
- [venturecraft/revisionable](https://github.com/venturecraft/revisionable)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
