<?php

namespace JPNut\Versioning\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JPNut\Versioning\Versionable;
use JPNut\Versioning\VersionableTrait;
use JPNut\Versioning\VersionOptions;

class Dummy extends Model implements Versionable
{
    use VersionableTrait;

    protected $table = 'dummies';
    protected $guarded = [];
    public $timestamps = true;

    /**
     * @return VersionOptions
     */
    public function getVersionableOptions(): VersionOptions
    {
        return VersionOptions::create()
            ->setVersionableAttributes(['email', 'city', 'role_id']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}