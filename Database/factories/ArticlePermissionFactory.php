<?php

use App\Modules\Core\Article;
use App\Modules\Core\ArticlePermission;
use App\Modules\Core\User;
use Illuminate\Support\Str;
use Faker\Generator as Faker;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(ArticlePermission::class, function (Faker $faker) {
    return [
        'article_id' => $faker->randomNumber(),
        'user_id' => $faker->randomAscii
    ];
});
