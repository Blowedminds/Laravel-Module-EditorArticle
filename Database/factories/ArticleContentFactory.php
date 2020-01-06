<?php

use App\Modules\Core\Article;
use App\Modules\Core\ArticleContent;
use App\Modules\Core\Language;
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

$factory->define(ArticleContent::class, function (Faker $faker) {
    return [
        'article_id' => static function() {
            return factory(Article::class)->create()->id;
        },
        'title' => $faker->title,
        'language_id' => static function() {
            return factory(Language::class)->create()->id;
        },
        'body' => $faker->text,
        'sub_title' => $faker->sentence,
        'keywords' => [$faker->word, $faker->word],
        'published' => true,
        'version' => $faker->numberBetween(0, 100),
    ];
});
