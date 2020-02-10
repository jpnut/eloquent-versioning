<?php

namespace JPNut\Versioning;

interface Versionable
{
    /**
     * @return VersionOptions
     */
    public function getVersionableOptions(): VersionOptions;

    /**
     * @return array|string[]
     */
    public function defaultVersionSelect(): array;

    /**
     * @return string
     */
    public function getVersionKeyName(): string;

    /**
     * @return string
     */
    public function getQualifiedVersionKeyName(): string;

    /**
     * @return int
     */
    public function getVersionKey(): int;

    /**
     * @param int $version
     *
     * @return mixed
     */
    public function setVersionKey(int $version);

    /**
     * @return string
     */
    public function getVersionTable(): string;

    /**
     * @return string
     */
    public function getVersionTableKeyName(): string;

    /**
     * @return string
     */
    public function getQualifiedVersionTableKeyName(): string;

    /**
     * @return string
     */
    public function getVersionTableForeignKeyName(): string;

    /**
     * @return string
     */
    public function getQualifiedVersionTableForeignKeyName(): string;

    /**
     * @return string|null
     */
    public function getVersionTableCreatedAtName(): string;

    /**
     * @return string
     */
    public function getQualifiedVersionTableCreatedAtName(): string;

    /**
     * @return array
     */
    public function getVersionableAttributes(): array;

    /**
     * @return array
     */
    public function getQualifiedVersionableAttributes(): array;
}
