<?php

namespace App\Modules\Article\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Modules\Core\Article;
use App\Modules\Core\ArticleCategory;
use App\Modules\Core\ArticleContent;
use App\Modules\Core\Language;
use App\Modules\Core\ArticleArchive;
use App\Modules\Core\ArticlePermission;
use App\Modules\Core\Permission;
use App\Modules\Core\User;
use App\Modules\Core\UserData;
use App\Modules\Core\Role;

class ArticleController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api', 'permission:ownership.article']);

        $this->middleware('article.permission')->only([
            'getArticle',
            'putArticle',
            'putArticleContent',
            'postRestore',
            'getArticleContent',
            'deleteArticle',
            'deleteForceDelete',
        ]);
    }

    public function getArticlesPaginate()
    {
        $articles = auth()->user()
            ->articles()
            ->with([
                'categories',
                'contents' => function ($query) {
                    $query->with('language');
                },
                'author' => function ($query) {
                    $query->select('user_id', 'name');
                }
            ])
            ->orderBy('created_at', 'DESC')
            ->paginate(request()->input('per-page') ?? 20);

        return response()->json($articles, 200);
    }

    public function getArticle($article_slug)
    {
        return Article::slug($article_slug)
            ->with([
                'author' => function ($query) {
                    $query->select('user_id', 'name');
                },
                'contents' => function ($query) {
                    $query->select('article_id', 'language_id');
                },
                'categories' => function ($query) {
                    $query->select('categories.id', 'categories.name');
                }
            ])
            ->first();
    }

    public function getTrashedArticlesPaginate()
    {
        $trashedArticle = auth()->user()
            ->trashedArticles()
            ->with([
                'trashed_contents',
                'author' => function ($query) {
                    $query->select('user_id', 'name');
                }
            ])
            ->paginate(request()->input('per-page') ?? 20);

        return response()->json($trashedArticle, 200);
    }

    public function getArticleContent($article_slug, $language_slug)
    {
        $language_id = Language::slug($language_slug)->first()->id;

        $article = Article::slug($article_slug)->withContent($language_id)->first();

        return response()->json($article, 200);
    }

    public function deleteArticle($article_slug)
    {
        Article::slug($article_slug)->firstOrFail()->delete();

        return response()->json();
    }

    public function postArticle()
    {
        request()->validate([
            'title' => 'required',
            'sub_title' => 'required',
            'body' => 'required',
            'keywords' => 'present',
            'published' => 'required',
            'language_id' => 'required',
            'categories' => 'present',
            'image' => 'required',
            'slug' => 'required'
        ]);

        $user = auth()->user();

        $article = Article::create([
            'slug' => request()->input('slug'),
            'author_id' => $user->user_id,
            'image' => request()->input('image')
        ]);

        foreach (request()->input('categories') as $category_id) {
            ArticleCategory::create([
                'article_id' => $article->id,
                'category_id' => $category_id
            ]);
        }

        $article_language = ArticleContent::create([
            'article_id' => $article->id,
            'title' => request()->input('title'),
            'language_id' => request()->input('language_id'),
            'body' => request()->input('body'),
            'sub_title' => request()->input('sub_title'),
            'keywords' => request()->input('keywords'),
            'published' => (request()->input('published') == "1") ? 1 : 0,
            'version' => 1
        ]);

        $permission = ArticlePermission::create([
            'article_id' => $article->id,
            'user_id' => $user->user_id
        ]);

        return response()->json();
    }

    public function postArticleContent($article_slug, $language_slug)
    {
        request()->validate([
            'title' => 'required',
            'sub_title' => 'required',
            'body' => 'required',
            'keywords' => 'required',
            'published' => 'required',
        ]);

        $language_id = Language::slug($language_slug)->firstOrFail()->id;

        $article = Article::slug($article_slug)
            ->withContent($language_id)->firstOrFail();

        if ($article->content) {
            return response()->json(['header' => 'Error', 'message' => 'Already exist', 'state' => 'error'], 421);
        }

        $inputs = request()->all();

        $inputs['article_id'] = $article->id;
        $inputs['language_id'] = $language_id;
        $inputs['version'] = 1;

        ArticleContent::create($inputs);

        return response()->json();
    }

    public function putArticleContent($article_slug, $language_slug)
    {
        request()->validate([
            'title' => 'required',
            'sub_title' => 'required',
            'body' => 'required',
            'keywords' => 'required',
            'published' => 'required'
        ]);

        $language_id = Language::slug($language_slug)->firstOrFail()->id;

        $article = Article::slug($article_slug)->withContent($language_id)->whereHasContent($language_id)->firstOrFail();

        if (
            $article->content->title != request()->input('title') ||
            $article->content->sub_title != request()->input('sub_title') ||
            $article->content->body != request()->input('body') ||
            $article->content->keywords != request()->input('keywords')
        ) {
            ArticleArchive::create($article->content->toArray());

            $article->content->version = $article->content->version + 1;
        }

        $article->content->update(request()->only([
            'title', 'sub_title', 'body', 'keywords', 'published'
        ]));

        return response()->json();
    }

    public function putArticle($article_slug)
    {
        request()->validate([
            'slug' => 'required',
            'image' => 'required',
            'categories' => 'present'
        ]);

        $article = Article::slug($article_slug)->firstOrFail();

        ArticleCategory::where('article_id', $article->id)->forceDelete();

        $request_categories = request()->input('categories');

        foreach ($request_categories as $key => $value) {
            ArticleCategory::create([
                'article_id' => $article->id,
                'category_id' => $value
            ]);
        }

        $article->update(request()->only([
            'slug', 'image'
        ]));

        return response()->json();
    }

    public function postRestore($article_slug)
    {
        Article::onlyTrashed()->slug($article_slug)->firstOrFail()->restore();

        return response()->json();
    }

    public function deleteForceDelete($article_id)
    {
        Article::onlyTrashed()->findOrFail($article_id)->forceDelete();

        return response()->json();
    }

    public function getPermission($article_id)
    {
        $user = auth()->user();

        $article_users = Article::with(['users' => function ($query) {
            $query->whereHas('roles', function ($query_role) {
                $query_role->where('user_datas.role_id', '>', 1);
            })->select(['users.user_id', 'users.name']);
        }])->findOrFail($article_id)->users;

        $users = User::whereHas('roles', function ($query) {
            $query->where('role_id', '>', 1);
        })->get(['user_id', 'name']);

        $users = $users->map(function ($user) use ($article_users) {
            $user->permission = false;

            foreach ($article_users as $perm_user) {
                if ($user->user_id == $perm_user->user_id) {
                    $user->permission = true;
                }
            }
            return $user;
        });

        return response()->json(['users' => $users, 'permissions' => $article_users], 200);
    }

    public function putPermission($article_id)
    {
        request()->validate([
            'permissions' => 'present'
        ]);

        $user = auth()->user();

        $article = Article::with(['users' => function ($query) {
            $query->whereHas('roles', function ($query_role) {
                $query_role->where('user_datas.role_id', '>', 1);
            })->select('users.user_id');
        }])->findOrFail($article_id);

        if ($article->author_id != $user->user_id && !$role = $user->rolesByRoleId(1)->first())
            return response()->json([], 403);

        ArticlePermission::whereIn('user_id', $article->users)->where('article_id', $article_id)->delete();

        $users = User::whereHas('roles', function ($query) {
            $query->where('role_id', '>', 1);
        })->whereIn('user_id', request()->input('permissions'))->get();

        foreach ($users as $user) {
            ArticlePermission::create(['article_id' => $article_id, 'user_id' => $user->user_id]);
        }

        return response()->json();
    }
}
