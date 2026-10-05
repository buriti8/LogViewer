<?php

declare(strict_types=1);

namespace Arcanedev\LogViewer\Tests;

/**
 * Class     RoutesTest
 *
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class RoutesTest extends TestCase
{
    /* -----------------------------------------------------------------
     |  Tests
     | -----------------------------------------------------------------
     */

    /** @test */
    public function it_can_see_dashboard_page(): void
    {
        $response = $this->get(route('log-viewer::dashboard'));
        $response->assertSuccessful();

        static::assertStringContainsString(
            '<h1>Dashboard</h1>',
            $response->getContent()
        );
    }

    /** @test */
    public function it_can_see_logs_page(): void
    {
        $response = $this->get(route('log-viewer::logs.list'));
        $response->assertSuccessful();

        static::assertStringContainsString(
            '<h1>Logs</h1>',
            $response->getContent()
        );
        // TODO: Add more assertion => list all logs
    }

    /** @test */
    public function it_can_show_a_log_page(): void
    {
        $date = '2015-01-01';

        $response = $this->get(route('log-viewer::logs.show', [$date]));
        $response->assertSuccessful();

        static::assertStringContainsString(
            "<h1>Log [{$date}]</h1>",
            $response->getContent()
        );
        // TODO: Add more assertion => list all log entries
    }

    /** @test */
    public function it_can_see_a_filtered_log_entries_page(): void
    {
        $date     = '2015-01-01';
        $level    = 'error';

        $response = $this->get(route('log-viewer::logs.filter', [$date, $level]));
        $response->assertSuccessful();

        static::assertStringContainsString(
            "<h1>Log [{$date}]</h1>",
            $response->getContent()
        );
        // TODO: Add more assertion => log entries is filtered by a level
    }

    /** @test */
    public function it_can_search_if_log_entries_contains_same_header_page(): void
    {
        $date     = '2015-01-01';
        $level    = 'all';
        $query    = 'This is an error log.';

        $response = $this->get(route('log-viewer::logs.search', compact('date', 'level', 'query')));
        $response->assertSuccessful();

        /** @var \Illuminate\View\View $view */
        $view = $response->getOriginalContent();

        static::assertArrayHasKey('entries', $view->getData());

        /** @var  \Illuminate\Pagination\LengthAwarePaginator  $entries */
        $entries = $view->getData()['entries'];

        static::assertCount(1, $entries);
    }

    /** @test */
    public function it_can_search_using_shuffled_query(): void
    {
        $date     = '2015-01-01';
        $level    = 'all';
        $query    = explode(' ', 'This is a error log');
        shuffle($query);
        $query    = implode(' ', $query);

        $response = $this->get(route('log-viewer::logs.search', compact('date', 'level', 'query')));
        $response->assertSuccessful();

        /** @var \Illuminate\View\View $view */
        $view = $response->getOriginalContent();

        static::assertArrayHasKey('entries', $view->getData());

        /** @var  \Illuminate\Pagination\LengthAwarePaginator  $entries */
        $entries = $view->getData()['entries'];

        static::assertCount(1, $entries);
    }

    /** @test */
    public function it_can_search_using_case_insensitive_query(): void
    {
        $date     = '2015-01-01';
        $level    = 'all';
        $query    = explode(' ', 'ThiS Is A ErROr loG');
        shuffle($query);
        $query    = implode(' ', $query);

        $response = $this->get(route('log-viewer::logs.search', compact('date', 'level', 'query')));
        $response->assertSuccessful();

        /** @var \Illuminate\View\View $view */
        $view = $response->getOriginalContent();

        static::assertArrayHasKey('entries', $view->getData());

        /** @var  \Illuminate\Pagination\LengthAwarePaginator  $entries */
        $entries = $view->getData()['entries'];

        static::assertCount(1, $entries);
    }

    /** @test */
    public function it_can_still_search_if_extra_spacing_is_in_query(): void
    {
        $date     = '2015-01-01';
        $level    = 'all';
        $query    = explode(' ', 'ThiS  Is  A  ErROr  loG');
        shuffle($query);
        $query    = implode(' ', $query);

        $response = $this->get(route('log-viewer::logs.search', compact('date', 'level', 'query')));
        $response->assertSuccessful();

        /** @var \Illuminate\View\View $view */
        $view = $response->getOriginalContent();

        static::assertArrayHasKey('entries', $view->getData());

        /** @var  \Illuminate\Pagination\LengthAwarePaginator  $entries */
        $entries = $view->getData()['entries'];

        static::assertCount(1, $entries);
    }

    /** @test */
    public function it_must_redirect_if_search_query_is_not_available(): void
    {
        $date  = '2015-01-01';
        $level = 'notice';

        $this->get(route('log-viewer::logs.search', compact('date', 'level')))
             ->assertRedirect(route('log-viewer::logs.show', [$date]));
    }

    /** @test */
    public function it_must_redirect_on_all_level(): void
    {
        $date     = '2015-01-01';
        $level    = 'all';

        $response = $this->get(route('log-viewer::logs.filter', [$date, $level]));

        static::assertTrue($response->isRedirection());
        static::assertEquals(302, $response->getStatusCode());
        // TODO: Add more assertion to check the redirect url
    }

    /** @test */
    public function it_can_download_a_log_page(): void
    {
        $date = '2015-01-01';

        $response = $this->get(route('log-viewer::logs.download', [$date]));
        $response->assertSuccessful();

        /** @var  \Symfony\Component\HttpFoundation\BinaryFileResponse  $base */
        $base = $response->baseResponse;

        static::assertInstanceOf(
            \Symfony\Component\HttpFoundation\BinaryFileResponse::class, $base
        );
        static::assertEquals("laravel-$date.log", $base->getFile()->getFilename());
    }

    /** @test */
    public function it_can_delete_a_log(): void
    {
        static::createDummyLog(
            $date = date('Y-m-d'),
            $path = storage_path('logs')
        );

        $this->app['config']->set(['log-viewer.storage-path' => $path]);

        $this
            ->call('DELETE', route('log-viewer::logs.delete', compact('date')), [], [], [], ['HTTP_X-Requested-With' => 'XMLHttpRequest'])
            ->assertSuccessful()
            ->assertExactJson([
                'result' => 'success',
            ])
        ;
    }

    /** @test */
    public function it_must_throw_log_not_found_exception_on_show(): void
    {
        $response = $this->get(route('log-viewer::logs.show', ['0000-00-00']));

        static::assertInstanceOf(
            \Symfony\Component\HttpKernel\Exception\HttpException::class,
            $response->exception
        );

        static::assertSame(404, $response->getStatusCode());
        static::assertSame('Log not found in this date [0000-00-00]', $response->exception->getMessage());
    }

    /** @test */
    public function it_must_throw_log_not_found_exception_on_delete(): void
    {
        $response = $this->delete(route('log-viewer::logs.delete'), ['date' => '0000-00-00'], ['HTTP_X-Requested-With' => 'XMLHttpRequest']);

        static::assertInstanceOf(\Arcanedev\LogViewer\Exceptions\FilesystemException::class, $response->exception);
        static::assertStringStartsWith('The log(s) could not be located at : ', $response->exception->getMessage());
    }

    /** @test */
    public function it_must_throw_method_not_allowed_on_delete(): void
    {
        $response = $this->delete(route('log-viewer::logs.delete'));
        $response->assertStatus(405);

        static::assertInstanceOf(
            \Symfony\Component\HttpKernel\Exception\HttpException::class,
            $response->exception
        );
        static::assertSame('Method Not Allowed', $response->exception->getMessage());
    }

    /** @test */
    public function it_serves_the_compiled_assets_of_the_package(): void
    {
        $this->get(route('log-viewer::assets', ['path' => 'css/log-viewer.css']))
             ->assertSuccessful()
             ->assertHeader('Content-Type', 'text/css; charset=UTF-8');

        $this->get(route('log-viewer::assets', ['path' => 'js/log-viewer.js']))
             ->assertSuccessful();

        $this->get(route('log-viewer::assets', ['path' => 'webfonts/fa-solid-900.woff2']))
             ->assertSuccessful();
    }

    /** @test */
    public function it_does_not_serve_files_outside_of_the_allowed_assets(): void
    {
        $this->get(route('log-viewer::assets', ['path' => '../composer.json']))->assertNotFound();
        $this->get(route('log-viewer::assets', ['path' => 'css/missing.css']))->assertNotFound();
    }

    /** @test */
    public function it_renders_the_pages_with_the_package_assets(): void
    {
        $this->get(route('log-viewer::dashboard'))
             ->assertSee(route('log-viewer::assets', ['path' => 'css/log-viewer.css']), false)
             ->assertSee(route('log-viewer::assets', ['path' => 'js/log-viewer.js']), false);
    }

    /** @test */
    public function it_uses_the_configured_favicon(): void
    {
        $this->get(route('log-viewer::dashboard'))->assertDontSee('rel="icon"', false);

        config(['log-viewer.favicon' => 'img/favicon.png']);

        $this->get(route('log-viewer::dashboard'))->assertSee('rel="icon" href="'.asset('img/favicon.png').'"', false);
    }

    /** @test */
    public function it_validates_the_date_when_clearing_a_log(): void
    {
        $this->post(route('log-viewer::logs.clear'), ['date' => 'invalid'])
             ->assertSessionHasErrors('date');
    }
}
