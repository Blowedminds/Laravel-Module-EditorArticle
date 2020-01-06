<?php

Route::get('articles/paginate', 'ArticleController@getArticlesPaginate');

Route::get('articles/trashed/paginate', 'ArticleController@getTrashedArticlesPaginate');

Route::prefix('article')->group(function () {

    Route::post('', 'ArticleController@postArticle');


    Route::get('{article_slug}', 'ArticleController@getArticle');

    Route::put('{article_slug}', 'ArticleController@putArticle');

    Route::delete('{article_slug}', 'ArticleController@deleteArticle');

});

Route::post('restore/{article_slug}', 'ArticleController@postRestore');

Route::get('content/{article_slug}/{language_slug}', 'ArticleController@getArticleContent');

Route::post('content/{article_slug}/{language_slug}', 'ArticleController@postArticleContent');

Route::put('content/{article_slug}/{language_slug}', 'ArticleController@putArticleContent');

Route::delete('force-delete/{article_slug}', 'ArticleController@deleteForceDelete');

Route::get('permission/{article_slug}', 'ArticleController@getPermission');

Route::put('permission/{article_slug}', 'ArticleController@putPermission');
