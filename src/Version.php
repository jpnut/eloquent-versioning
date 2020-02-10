<?php

namespace JPNut\Versioning;

use Illuminate\Database\Eloquent\Model;

class Version extends Model
{
    protected $primaryKey = 'version';

    protected $guarded = [];

    const UPDATED_AT = null;
}