# Laravel-Module-Auth

This module supports article management backend for Angular-Module-Article

**Required packages**
*--no required packages--*

**Required Modules**
1. Laravel-Module-Core

**Functionalities**
1. Add, Update, Move to Trash, Delete article
2. Support multilingual article.
3. Manage article permission, which user can edit.

**Installation**
1. Add the module to Laravel project as a submodule. 
`git submodule add https://github.com/bwqr/Laravel-Module-Article app/Modules/Article`
2. Add the route file `Http/auth.php` to `app/Providers/RouteServiceProvider.php`
 and register inside the `map` function, eg.  
 `
    protected function mapArticleRoutes()
    {
        Route::prefix('article')
            ->middleware('api')
            ->namespace($this->moduleNamespace . "\Article\Http\Controllers")
            ->group(base_path('app/Modules/Article/Http/article.php'));
    }
 `
3. Migrate the database. `php artisan migrate --path=/app/Modules/Article/Database/migrations`
4. Add `Observers/ArticleObserver` to `app/Providers/AppServiceProvider.php` file 
in boot function. eg, `Article::observe(ArticleObserver::class)`
