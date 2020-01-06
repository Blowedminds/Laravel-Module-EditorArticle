<?php


namespace App\Modules\Article\Test\Feature;


use App\Modules\Core\Article;
use App\Modules\Core\ArticleContent;
use App\Modules\Core\ArticlePermission;
use App\Modules\Core\Tests\TestCase;
use App\Modules\Core\User;

class ArticleModuleTest extends TestCase
{
    private $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();

        $this->withoutMiddleware([\App\Modules\Core\Http\Middleware\Permission::class]);
    }

    public function testRoutes(): void
    {
        $this->assertTrue($this->checkRoute($this->articleRoute . 'articles/paginate'));
        $this->assertTrue($this->checkRoute($this->articleRoute . 'articles/trashed/paginate'));
        $this->assertTrue($this->checkRoute($this->articleRoute . 'article/{article_slug}'));
        $this->assertTrue($this->checkRoute($this->articleRoute . 'article', 'post'));
        $this->assertTrue($this->checkRoute($this->articleRoute . 'article/{article_slug}', 'put'));
        $this->assertTrue($this->checkRoute($this->articleRoute . 'article/{article_slug}', 'delete'));
        $this->assertTrue($this->checkRoute($this->articleRoute . 'restore/{article_slug}', 'post'));
        $this->assertTrue($this->checkRoute($this->articleRoute . 'content/{article_slug}/{language_slug}'));
        $this->assertTrue($this->checkRoute($this->articleRoute . 'content/{article_slug}/{language_slug}', 'post'));
        $this->assertTrue($this->checkRoute($this->articleRoute . 'content/{article_slug}/{language_slug}', 'put'));
        $this->assertTrue($this->checkRoute($this->articleRoute . 'force-delete/{article_slug}', 'delete'));
        $this->assertTrue($this->checkRoute($this->articleRoute . 'permission/{article_slug}', 'get'));
        $this->assertTrue($this->checkRoute($this->articleRoute . 'permission/{article_slug}', 'put'));
    }

    public function testGetArticlesPaginate(): void
    {
        $count = random_int(1, 10);

        for ($i = 0; $i < $count; $i++) {
            $article = factory(Article::class)->create();
            factory(ArticlePermission::class)->create([
                'article_id' => $article->id,
                'user_id' => $this->user->user_id
            ]);
        }

        $route = $this->articleRoute . 'articles/paginate?per-page=' . $count;

        $response = $this->actingAs($this->user)->getJson($route);

        $response->assertStatus(200);

        $data = json_decode($response->getContent(), true);

        $this->assertCount($count, $data['data']);
    }

    public function testTrashedArticles()
    {
        $count = random_int(1, 10);

        for ($i = 0; $i < $count; $i++) {
            $article = factory(Article::class)->create();
            factory(ArticlePermission::class)->create([
                'article_id' => $article->id,
                'user_id' => $this->user->user_id
            ]);

            $article->delete();
        }

        $route = $this->articleRoute . 'articles/trashed/paginate';

        $response = $this->actingAs($this->user)->getJson($route);

        $response->assertStatus(200);

        $data = json_decode($response->getContent(), true);

        $this->assertCount($count, $data['data']);
    }

    public function testGetArticle()
    {
        $article = factory(Article::class)->create([
            'author_id' => $this->user->user_id
        ]);
        //Not having permission
        $this->actingAs($this->user)->getJson($this->articleRoute . 'article/' . $article->slug)->assertStatus(401);

        factory(ArticlePermission::class)->create([
            'article_id' => $article->id,
            'user_id' => $this->user->user_id
        ]);
        //Having permission
        $response = $this->actingAs($this->user)->getJson($this->articleRoute . 'article/' . $article->slug);

        $response->assertStatus(200);

        $this->assertDatabaseHas($article->getTable(), ['id' => json_decode($response->getContent(), true)['id']]);
    }

    public function testPostArticle()
    {
        $articleContent = factory(ArticleContent::class)->make()->toArray();

        $this->postTest(Article::class, $this->articleRoute . 'article', $this->user, array_merge($articleContent, [
            'categories' => []
        ]), ['views', 'author_id']);

        $this->assertDatabaseHas('article_contents', ['title' => $articleContent['title']]);
    }

    public function testPutArticle()
    {
        $article = factory(Article::class)->create([
            'author_id' => $this->user->user_id
        ]);

        $data = array_merge(factory(Article::class)->make()->toArray(), ['categories' => []]);
        //Not having permission
        $this->actingAs($this->user)->putJson($this->articleRoute . 'article/' . $article->slug, $data)->assertStatus(401);

        $this->assertDatabaseHas($article->getTable(), ['slug' => $article->slug]);

        factory(ArticlePermission::class)->create([
            'article_id' => $article->id,
            'user_id' => $this->user->user_id
        ]);
        //Having permission
        $this->actingAs($this->user)->putJson($this->articleRoute . 'article/' . $article->slug, $data)->assertStatus(200);
        $this->assertDatabaseMissing($article->getTable(), ['slug' => $article->slug]);
    }

    public function testDeleteArticle()
    {
        $article = factory(Article::class)->create([
            'author_id' => $this->user->user_id
        ]);

        //Not having permission
        $this->actingAs($this->user)->deleteJson($this->articleRoute . 'article/' . $article->slug)->assertStatus(401);

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'deleted_at' => null]);

        factory(ArticlePermission::class)->create([
            'article_id' => $article->id,
            'user_id' => $this->user->user_id
        ]);

        //Having permission
        $this->actingAs($this->user)->deleteJson($this->articleRoute . 'article/' . $article->slug)->assertStatus(200);

        $this->assertDatabaseMissing('articles', ['id' => $article->id, 'deleted_at' => null]);
    }

    public function testRestoreArticle()
    {
        $article = factory(Article::class)->create();

        $article->delete();

        //Not having permission
        $this->actingAs($this->user)->postJson($this->articleRoute . 'restore/' . $article->slug)->assertStatus(401);

        $this->assertDatabaseHas($article->getTable(), ['id' => $article->id, 'deleted_at' => $article->deleted_at]);

        factory(ArticlePermission::class)->create([
            'article_id' => $article->id,
            'user_id' => $this->user->user_id
        ]);

        //Having permission
        $this->actingAs($this->user)->postJson($this->articleRoute . 'restore/' . $article->slug)->assertStatus(200);

        $this->assertDatabaseHas($article->getTable(), ['id' => $article->id, 'deleted_at' => null]);
    }
}
