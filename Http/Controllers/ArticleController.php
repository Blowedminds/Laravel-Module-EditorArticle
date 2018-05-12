<?php

namespace App\Modules\Editor\Article\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Article;
use App\ArticleCategory;
use App\ArticleContent;
use App\Language;
use App\ArticleArchive;
use App\ArticlePermission;
use App\UserData;
use App\Role;

class ArticleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');

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
            'language_id' => 'required',
        ]);

        $language_id = request()->input('language_id');

        $article = Article::where('id', $article_id)
            ->withContent($language_id)->firstOrFail();

        if ($article->content) {
            return response()->json(['header' => 'Error', 'message' => 'Already exist', 'state' => 'error'], 421);
        }

        $inputs = request()->all();

        $inputs['article_id'] = $article_id;
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

        $response = "Makaleniz başarı ile güncellendi!";

        if (
            $article->content->title != request()->input('title') ||
            $article->content->sub_title != request()->input('sub_title') ||
            $article->content->body != request()->input('body') ||
            $article->content->keywords != request()->input('keywords')
        ) {
            ArticleArchive::create($article->content->toArray());

            $response = "Makaleniz başarı ile güncellendi, eskisinin kopyası saklandı!";

            $article->content->version = $article->content->version + 1;
        }

        $article->content->update(request()->only([
            'title', 'sub_title', 'body', 'keywords', 'published', 'language_id'
        ]));

        return response()->json([
            'header' => 'İşlem Başarılı',
            'message' => $response,
            'state' => 'success'
        ], 200);
    }

    public function postArticle($article_id)
    {
        request()->validate([
            'slug' => 'required',
            'categories' => 'required',
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

        $article = Article::find($article_id);

        $article_permission = $article->users;

        if ($article->author_id != $user->user_id && !$role = $user->rolesByRoleId(1)->first())
            return response()->json([
                'header' => 'Yetkisiz İşlem', 'message' => 'Bu makaleyi düzenlemeye yetkiniz yok!', 'state' => 'error'
            ]);

        $data = ['users' => [], 'permission' => []];
        $i = 0;

        $roles = Role::where('id', '>', 1)->get();

        foreach ($roles as $key => $value) {
            $temp_data = $value->users;

            foreach ($temp_data as $key => $value) {
                if ($user->user_id == $value->user_id) continue;

                $data['users'][$i]['name'] = $value->name;
                $data['users'][$i]['user_id'] = $value->user_id;
                $i++;
            }
        }

        $i = 0;

        foreach ($article_permission as $key => $value) {
            if ($user->user_id == $value->user_id) continue;

            $data['permission'][$i]['user_id'] = $value->user_id;
            $data['permission'][$i]['name'] = $value->name;
            $i++;
        }

        return response()->json($data, 200);
    }

    public function putPermission($article_id)
    {
        request()->validate([
            'have_permission' => 'present|array',
            'not_have_permission' => 'present|array'
        ]);

        $have_permission = request()->input('have_permission');
        $not_have_permission = request()->input('not_have_permission');

        $user = auth()->user();

        $article = Article::find($article_id);

        $article_permission = $article->users;

        if ($article->author_id != $user->user_id && !$role = $user->rolesByRoleId(1)->first())
            return response()->json([
                'header' => 'Yetkisiz İşlem', 'message' => 'Bu makaleyi düzenlemeye yetkiniz yok!', 'state' => 'error'
            ]);

        foreach ($have_permission as $key => $value) {
            if ($temp = UserData::where('user_id', $value['user_id'])->where('role_id', 1)->first()) continue;

            ArticlePermission::firstOrCreate(
                ['article_id' => $article_id, 'user_id' => $value['user_id']], ['article_id' => $article_id, 'user_id' => $value['user_id']]
            );
        }

        foreach ($not_have_permission as $key => $value) {
            if ($value['user_id'] == $user->user_id) continue;

            if ($temp = UserData::where('user_id', $value['user_id'])->where('role_id', 1)->first()) continue;

            if ($exist = ArticlePermission::where('article_id', $article_id)->where('user_id', $value['user_id'])->first())
                $exist->delete();
        }

        return response()->json([
            'header' => 'İşlem Başarılı', 'message' => 'İzinler başarı ile güncellendi!', 'state' => 'success'
        ], 200);
    }
}
