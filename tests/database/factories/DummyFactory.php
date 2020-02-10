<?php

use Faker\Generator as Faker;
use JPNut\Versioning\Tests\Models\Dummy;

/*
|--------------------------------------------------------------------------
| User Factories
|--------------------------------------------------------------------------
|
*/
$factory->define(Dummy::class, function (Faker $faker) {
    return [
        'email' => $faker->unique()->safeEmail,
        'name'  => $faker->userName,
        'city'  => $faker->city,
    ];
});
