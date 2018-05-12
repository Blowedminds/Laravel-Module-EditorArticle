<?php


namespace App\Observers;


use App\Article;
use App\ArticleRoom;

class ArticleObserver
{
    public function created(Article $article)
    {
        ArticleRoom::firstOrCreate(['article_id' => $article->id], [
            'article_id' => $article->id,
        ]);
    }

    public function restoring(Article $article)
    {
        $article->trashed_contents()->restore();

        $article->trashed_categories()->restore();
    }

    public function deleting(Article $article)
    {
        $article->contents()->delete();

        $article->article_categories()->delete();
    }

    public function forceDeleted(Article $article)
    {
        $article->contents()->forceDelete();

        $article->article_categories()->forceDelete();

        $article->trashed_contents()->forceDelete();

        $article->trashed_categories()->forceDelete();

        $article->olds()->forceDelete();

        $article->permissions()->delete();
    }
}