<?php

namespace JPNut\Versioning;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

trait VersionableTrait
{
    /**
     * @return void
     */
    public static function bootVersionableTrait()
    {
        static::addGlobalScope(new VersioningScope());
    }

    /**
     * Get a new query builder that doesn't have any global scopes or eager loading.
     *
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newModelQuery()
    {
        if (!$this->hasVersionJoin($builder = parent::newModelQuery(), $this->getVersionTable())) {
            $builder
                ->join(
                    $this->getVersionTable(),
                    function ($join) {
                        $join->on($this->getQualifiedKeyName(), '=', $this->getQualifiedVersionTableForeignKeyName())
                            ->on($this->getQualifiedVersionTableKeyName(), '=', $this->getQualifiedVersionKeyName());
                    }
                )
                ->select(
                    $this->defaultVersionSelect()
                );
        }

        return $builder;
    }

    /**
     * @return array|string[]
     */
    public function defaultVersionSelect(): array
    {
        return array_merge(
            [
                $this->getTable().'.*',
                $this->getQualifiedVersionTableKeyName(),
            ],
            $this->getQualifiedVersionableAttributes(),
        );
    }

    /**
     * Determine if the given builder contains a join with the given table.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param string                                $table
     *
     * @return bool
     */
    protected function hasVersionJoin(Builder $builder, string $table)
    {
        return collect($builder->getQuery()->joins)->pluck('table')->contains($table);
    }

    /**
     * Perform a model insert operation.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return bool
     */
    protected function performInsert(Builder $query)
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        // First we'll need to create a fresh query instance and touch the creation and
        // update timestamps on this model, which are maintained by us for developer
        // convenience. After, we will just continue saving these model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // Ensure that the initial version number is set to 1. This sets the attribute,
        // and will be synced into storage later.
        $this->setVersionKey(1);

        // If the model has an incrementing key, we can use the "insertGetId" method on
        // the query builder, which will give us back the final inserted ID for this
        // table from the database. Not all tables have to be incrementing though.
        $attributes = $this->getAttributes();

        // get version values & master attributes
        $versionAttributes = $this->getVersionAttributes($attributes);
        $masterAttributes = array_diff_key($attributes, $versionAttributes);

        if ($this->getIncrementing()) {
            $this->insertAndSetId($query, $masterAttributes);
        }

        // If the table isn't incrementing we'll simply insert these attributes as they
        // are. These attribute arrays must contain an "id" column previously placed
        // there by the developer as the manually determined key for these models.
        else {
            if (empty($masterAttributes)) {
                return true;
            }

            $query->insert($masterAttributes);
        }

        // insert the initial version into the version table
        $this->insertVersion($versionAttributes);

        // We will go ahead and set the exists property to true, so that it is set when
        // the created event is fired, just in case the developer tries to update it
        // during the event. This will allow them to do so and run an update here.
        $this->exists = true;

        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Perform a model update operation.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return bool
     */
    protected function performUpdate(Builder $query)
    {
        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // First we need to create a fresh query instance and touch the creation and
        // update timestamp on the model which are maintained by us for developer
        // convenience. Then we will just continue saving the model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        $dirty = $this->getDirty();

        if (count($dirty) === 0) {
            return true;
        }

        // get version values & master attributes
        $versionAttributes = $this->getVersionAttributes($dirty);
        $masterAttributes = array_diff_key($dirty, $versionAttributes);

        $shouldCreateNewVersion = $this->shouldCreateNewVersion($versionAttributes);

        $this->setKeysForSaveQuery($query)
            ->increment(
                $this->getVersionKeyName(),
                $shouldCreateNewVersion ? 1 : 0,
                $masterAttributes
            );

        // If we need to create a new version, we first of all must manually increment
        // the version counter. This ensures the value is synced with the database.
        // We also use this value to insert a new version to the versions table.
        if ($shouldCreateNewVersion) {
            $this->setVersionKey($this->getVersionKey() + 1);

            $this->insertVersion(
                array_merge(
                    $this->getVersionAttributes($this->getAttributes()),
                    $versionAttributes
                )
            );
        }

        $this->syncChanges();

        $this->fireModelEvent('updated', false);

        return true;
    }

    /**
     * Delete the model from the database.
     *
     * @throws \Exception
     *
     * @return bool|null
     */
    public function delete()
    {
        if (is_null($this->getKeyName())) {
            throw new Exception('No primary key defined on model.');
        }

        // If the model doesn't exist, there is nothing to delete so we'll just return
        // immediately and not do anything else. Otherwise, we will continue with a
        // deletion process on the model, firing the proper events, and so forth.
        if (!$this->exists) {
            return null;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        // Here, we'll touch the owning models, verifying these timestamps get updated
        // for the models. This will allow any caching to get broken on the parents
        // by the timestamp. Then we will go ahead and delete the model instance.
        $this->touchOwners();

        $this->performDeleteOnModel();

        // We'd like to remove all the versions associated with this model, but we need
        // to make sure the model wasn't soft deleted first.
        if (!$this->exists) {
            $this->versions()->delete();
        }

        // Once the model has been deleted, we will fire off the deleted event so that
        // the developers may hook into post-delete operations. We will then return
        // a boolean true as the delete is presumably successful on the database.
        $this->fireModelEvent('deleted', false);

        return true;
    }

    /**
     * @param array $attributes
     *
     * @return bool
     */
    protected function shouldCreateNewVersion(array $attributes): bool
    {
        return !empty($attributes);
    }

    /**
     * @param array $attributes
     *
     * @return mixed
     */
    protected function insertVersion(array $attributes)
    {
        return $this->versions()
            ->newModelInstance()
            ->forceFill(
                array_merge(
                    $attributes,
                    [
                        $this->getVersionTableForeignKeyName() => $this->getKey(),
                        $this->getVersionTableKeyName()        => $this->getVersionKey(),
                        $this->getVersionTableCreatedAtName()  => $this->freshTimestampString(),
                    ]
                )
            )
            ->save();
    }

    /**
     * @param array $attributes
     *
     * @return array
     */
    protected function getVersionAttributes(array $attributes)
    {
        $array = [];

        $versionableAttributes = $this->getVersionableAttributes();

        foreach ($attributes as $key => $value) {
            if ($newKey = $this->isVersionedKey($key, $versionableAttributes)) {
                $array[$newKey] = $value;
            }
        }

        return $array;
    }

    /**
     * Check if key is in versioned keys.
     *
     * @param string $key
     * @param array  $versionedKeys
     *
     * @return string|null
     */
    protected function isVersionedKey($key, array $versionedKeys)
    {
        return in_array($key, $versionedKeys)
            ? $key
            : null;
    }

    /**
     * @return VersionOptions
     */
    public function getVersionableOptions(): VersionOptions
    {
        return VersionOptions::create();
    }

    /**
     * @return string
     */
    public function getVersionKeyName(): string
    {
        return $this->getVersionableOptions()->versionKeyName;
    }

    /**
     * @return string
     */
    public function getQualifiedVersionKeyName(): string
    {
        return $this->getTable().'.'.$this->getVersionKeyName();
    }

    /**
     * @return int
     */
    public function getVersionKey(): int
    {
        return $this->getAttributeValue($this->getVersionKeyName());
    }

    /**
     * @param int $version
     *
     * @return mixed
     */
    public function setVersionKey(int $version)
    {
        return $this->setAttribute($this->getVersionKeyName(), $version);
    }

    /**
     * @return string
     */
    public function getVersionTable(): string
    {
        return $this->getVersionableOptions()->versionTableName ?? Str::singular($this->getTable()).'_versions';
    }

    /**
     * @return string
     */
    public function getVersionTableKeyName(): string
    {
        return $this->getVersionableOptions()->versionTableKeyName;
    }

    /**
     * @return string
     */
    public function getQualifiedVersionTableKeyName(): string
    {
        return $this->getVersionTable().'.'.$this->getVersionTableKeyName();
    }

    /**
     * @return string
     */
    public function getVersionTableForeignKeyName(): string
    {
        return $this->getVersionableOptions()->versionTableForeignKeyName;
    }

    /**
     * @return string
     */
    public function getQualifiedVersionTableForeignKeyName(): string
    {
        return $this->getVersionTable().'.'.$this->getVersionTableForeignKeyName();
    }

    /**
     * @return string|null
     */
    public function getVersionTableCreatedAtName(): string
    {
        return $this->getVersionableOptions()->versionTableCreatedAtName;
    }

    /**
     * @return string
     */
    public function getQualifiedVersionTableCreatedAtName(): string
    {
        return $this->getVersionTable().'.'.$this->getVersionTableCreatedAtName();
    }

    /**
     * @return array
     */
    public function getVersionableAttributes(): array
    {
        return $this->getVersionableOptions()->versionableAttributes;
    }

    /**
     * @return array
     */
    public function getQualifiedVersionableAttributes(): array
    {
        return array_map(
            function (string $attribute) {
                return $this->getVersionTable().'.'.$attribute;
            }, $this->getVersionableOptions()->versionableAttributes
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function versions(): HasMany
    {
        return $this->newHasMany(
            $this->newRelatedInstance(Version::class)
                ->setTable($this->getVersionTable())
                ->newQuery(),
            $this,
            $this->getQualifiedVersionTableForeignKeyName(),
            $this->getKeyName()
        );
    }

    /**
     * @param mixed $version
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function version($version)
    {
        if ($version instanceof Version) {
            return $version;
        }

        return $this->versions()
            ->whereKey($version)
            ->first();
    }

    /**
     * @param  $version
     *
     * @return mixed
     */
    public function changeVersion($version)
    {
        $this->setVersionKey($this->getVersionKey() + 1);

        $this->save();

        $this->insertVersion(
            $this->getVersionAttributes(
                $this->version($version)->getAttributes()
            )
        );

        return $this;
    }
}
