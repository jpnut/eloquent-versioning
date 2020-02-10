<?php

namespace JPNut\Versioning;

class VersionOptions
{
    /**
     * @var string
     */
    public string $versionKeyName;

    /**
     * @var string|null
     */
    public ?string $versionTableName;

    /**
     * @var string
     */
    public string $versionTableKeyName;

    /**
     * @var string
     */
    public string $versionTableForeignKeyName;

    /**
     * @var string
     */
    public string $versionTableCreatedAtName;

    /**
     * @var array
     */
    public array $versionableAttributes;

    /**
     * @param  array  $options
     */
    public function __construct(array $options = [])
    {
        $this->versionKeyName = $options['versionKeyName'] ?? 'version';
        $this->versionTableName = $options['versionTableName'] ?? null;
        $this->versionTableKeyName = $options['versionTableKeyName'] ?? 'version';
        $this->versionTableForeignKeyName = $options['versionTableForeignKeyName'] ?? 'parent_id';
        $this->versionTableCreatedAtName = $options['versionTableCreatedAtName'] ?? 'created_at';
        $this->versionableAttributes = $options['versionableAttributes'] ?? [];
    }

    /**
     * @param  array  $options
     * @return static
     */
    public static function create(array $options = []): self
    {
        return new static($options);
    }

    /**
     * @param  string  $fieldName
     * @return self
     */
    public function saveVersionKeyTo(string $fieldName): self
    {
        $this->versionKeyName = $fieldName;

        return $this;
    }

    /**
     * @param  string  $table
     * @return self
     */
    public function useVersionTable(string $table): self
    {
        $this->versionTableName = $table;

        return $this;
    }

    /**
     * @param  string  $fieldName
     * @return self
     */
    public function saveVersionTableKeyTo(string $fieldName): self
    {
        $this->versionTableKeyName = $fieldName;

        return $this;
    }

    /**
     * @param  string  $fieldName
     * @return self
     */
    public function saveVersionTableForeignKeyTo(string $fieldName): self
    {
        $this->versionTableForeignKeyName = $fieldName;

        return $this;
    }

    /**
     * @param  string|null  $fieldName
     * @return self
     */
    public function saveVersionTableCreatedAtTo(?string $fieldName): self
    {
        $this->versionTableCreatedAtName = $fieldName;

        return $this;
    }

    /**
     * @param  array  $attributes
     * @return self
     */
    public function setVersionableAttributes(array $attributes): self
    {
        $this->versionableAttributes = $attributes;

        return $this;
    }
}