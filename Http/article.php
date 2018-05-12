<?php

Route::get('articles', 'ArticleController@getArticles');

Route::get('trashed-articles', 'ArticleController@getTrashedArticles');

Route::put('', 'ArticleController@putArticle');

Route::post('restore/{article_id}', 'ArticleController@postRestore');

Route::get('{article_slug}', 'ArticleController@getArticle');

Route::post('{article_id}', 'ArticleController@postArticle');

Route::delete('{article_id}', 'ArticleController@deleteArticle');

Route::get('content/{article_slug}/{language_slug}', 'ArticleController@getArticleContent');

//TODO: Convert this also to content/article_id/language_slug
Route::post('content/{article_id}', 'ArticleController@postArticleContent');

Route::put('content/{article_id}', 'ArticleController@putArticleContent');

Route::delete('force-delete/{article_id}', 'ArticleController@deleteForceDelete');

Route::get('permission/{article_id}', 'ArticleController@getPermission');

Route::put('permission/{article_id}', 'ArticleController@putPermission');