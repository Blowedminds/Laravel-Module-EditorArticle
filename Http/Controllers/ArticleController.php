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
        $this->middleware(['auth:api', 'ownership.article']);

        $this->middleware('article.permission')->only([
            'getArticle',
            'postArticle',
            'postArticleContent',
            'postRestore',
            'getArticleContent',
            'deleteArticle',
            'deleteForceDelete',
        ]);
    }

    public function getArticles()
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
            ->paginate(request()->input('per-page') ?? 5);

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

    public function getTrashedArticles()
    {
        $trashedArticle = auth()->user()
            ->trashedArticles()
            ->with([
                'trashed_contents',
                'author' => function ($query) {
                    $query->select('user_id', 'name');
                }
            ])
            ->paginate(10);

        return response()->json($trashedArticle, 200);
    }

    public function getArticleContent($article_slug, $language_slug)
    {
        $language_id = Language::slug($language_slug)->first()->id;

        $article = Article::slug($article_slug)->withContent($language_id)->first();

        return response()->json($article, 200);
    }

    public function deleteArticle($article_id)
    {
        Article::findOrFail($article_id)->delete();

        return response()->json([
            'header' => 'İşlem Başarılı',
            'message' => 'Makaleniz çöpe taşındı!',
            'state' => 'success'
        ], 200);
    }

    public function putArticle()
    {
        request()->validate([
            'title' => 'required',
            'sub_title' => 'required',
            'body' => 'required',
            'keywords' => 'required',
            'published' => 'required',
            'language_id' => 'required',
            'category' => 'required',
            'image' => 'required',
            'slug' => 'required'
        ]);

        $user = auth()->user();

        $article = Article::create([
            'slug' => request()->input('slug'),
            'author_id' => $user->user_id,
            'image' => request()->input('image')
        ]);

        $request_categories = json_decode(request()->input('category'));

        foreach ($request_categories as $key => $value) {
            ArticleCategory::create([
                'article_id' => $article->id,
                'category_id' => $value
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

        return response()->json([
            'header' => 'İşlem Başarılı',
            'message' => 'Makaleniniz başarılı bir şekilde kaydedildi',
            'state' => 'success'
        ], 200);
    }

    public function putArticleContent($article_id)
    {
        request()->validate([
            'title' => 'required',
            'sub_title' => 'required',
            'body' => 'required',
            'keywords' => 'required',
            'published' => 'required',
            'language' => 'required',
        ]);

        $language_id = Language::slug(request()->input('language'))->firstOrFail()->id;

        $article = Article::where('id', $article_id)
            ->withContent($language_id)->firstOrFail();

        if ($article->content) {
            return response()->json(['header' => 'Error', 'message' => 'Already exist', 'state' => 'error'], 421);
        }

        $inputs = request()->all();

        $inputs['article_id'] = $article_id;
        $inputs['language_id'] = $language_id;
        $inputs['version'] = 1;

        ArticleContent::create($inputs);

        return response()->json([
            'header' => 'İşlem Başarılı',
            'message' => 'Makaleninize başarılı bir şekilde dil eklendi',
            'state' => 'success'
        ], 200);
    }

    public function postArticleContent($article_id)
    {
        request()->validate([
            'title' => 'required',
            'sub_title' => 'required',
            'body' => 'required',
            'keywords' => 'required',
            'published' => 'required',
            'language_id' => 'required',
        ]);

        $article = Article::whereId($article_id)->withContent(request()->input('language_id'))->first();

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
            'title', 'sub_title', 'body', 'keywords', 'published', 'language_id'
        ]));

        return response()->json([
            'header' => 'İşlem Başarılı',
            'message' => 'Makale Güncellendi',
            'action' => 'Tamam',
            'state' => 'success'
        ], 200);
    }

    public function postArticle($article_id)
    {
        request()->validate([
            'slug' => 'required',
            'image' => 'required'
        ]);

        $article = Article::findOrFail($article_id);

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

        return response()->json([
            'header' => 'İşlem Başarılı',
            'message' => 'Değişiklikler kaydedildi!',
            'action' => 'Tamam',
            'state' => 'success'
        ], 200);
    }

    public function postRestore($article_id)
    {
        Article::onlyTrashed()->findOrFail($article_id)->restore();

        return response()->json([
            'header' => 'İşlem Başarılı', 'message' => 'Başarılı bir şekilde geri yüklendi', 'state' => 'success'
        ], 200);
    }

    public function deleteForceDelete($article_id)
    {
        Article::onlyTrashed()->findOrFail($article_id)->forceDelete();

        return response()->json([
            'header' => 'İşlem Başarılı', 'message' => 'Makale veritabanından kaldırıldı', 'state' => 'success'
        ], 200);
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
        $user = auth()->user();

        $article = Article::with(['users' => function ($query) {
            $query->whereHas('roles', function ($query_role) {
                $query_role->where('user_datas.role_id', '>', 1);
            })->select('users.user_id');
        }])->findOrFail($article_id);

        if ($article->author_id != $user->user_id && !$role = $user->rolesByRoleId(1)->first())
            return response()->json([
                'header' => 'Yetkisiz İşlem', 'message' => 'Bu makaleyi düzenlemeye yetkiniz yok!', 'state' => 'error'
            ]);

        ArticlePermission::whereIn('user_id', $article->users)->where('article_id', $article_id)->delete();

        $users = User::whereHas('roles', function ($query) {
            $query->where('role_id', '>', 1);
        })->whereIn('user_id', request()->input('permissions')?? [])->get();

        foreach ($users as $user) {
            ArticlePermission::create(['article_id' => $article_id, 'user_id' => $user->user_id]);
        }

        return response()->json([
            'header' => 'İşlem Başarılı', 'message' => 'İzinler başarı ile güncellendi!', 'state' => 'success', 'action' => 'Tamam'
        ], 200);
    }
}
