<?php

use Faker\Generator as Faker;
use JPNut\Versioning\Tests\Models\Role;

/*
|--------------------------------------------------------------------------
| User Factories
|--------------------------------------------------------------------------
|
*/
$factory->define(Role::class, function (Faker $faker) {
    return [
        'name'  => $faker->name,
    ];
});