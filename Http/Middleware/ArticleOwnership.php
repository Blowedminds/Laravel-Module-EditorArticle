<?php

namespace App\Modules\Article\Http\Middleware;

use App\Exceptions\RestrictedAreaException;
use Closure;

class ArticleOwnership
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
        if(auth()->user()->permission('ownership.article')->count() < 1) {
            throw new RestrictedAreaException();
        }

        return $next($request);
    }
}
