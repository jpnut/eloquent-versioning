<?php

namespace JPNut\Versioning\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use JPNut\Versioning\Versionable;
use JPNut\Versioning\VersionableTrait;
use JPNut\Versioning\VersionOptions;

class DummyRenamed extends Model implements Versionable
{
    use VersionableTrait;

    protected $table = 'dummies_renamed';
    protected $guarded = [];
    public $timestamps = false;

    /**
     * @return VersionOptions
     */
    public function getVersionableOptions(): VersionOptions
    {
        return (new VersionOptions())
            ->saveRecordKeyTo('record')
            ->savePreviousKeyTo('previous_version')
            ->saveVersionTo('version_number')
            ->savePublishedAtTo('published')
            ->saveRetiredAtTo('retired');
    }
}
