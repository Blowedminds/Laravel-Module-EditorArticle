<?php

namespace App\Modules\Article\Http\Middleware;

use App\Modules\Core\Article;
use Closure;

class ArticlePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $article_id = $request->route('article_id') ?
            $request->route('article_id') :
            Article::withTrashed()->slug($request->route('article_slug'))->firstOrFail(['id'])->id;

        $permission = \App\Modules\Core\ArticlePermission::where('article_id', $article_id)
            ->where('user_id', auth()->user()->user_id)
            ->count();

        if ($permission < 1) {

            return response()->json([], 401);
        }

        return $next($request);
    }
}
