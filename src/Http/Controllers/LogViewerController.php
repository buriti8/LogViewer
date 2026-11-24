<?php

declare(strict_types=1);

namespace Arcanedev\LogViewer\Http\Controllers;

use Arcanedev\LogViewer\Contracts\LogViewer as LogViewerContract;
use Arcanedev\LogViewer\Entities\{LogEntry, LogEntryCollection};
use Arcanedev\LogViewer\Exceptions\LogNotFoundException;
use Arcanedev\LogViewer\Tables\StatsTable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\{Arr, Collection, Str};

/**
 * Class     LogViewerController
 *
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class LogViewerController extends Controller
{
    /* -----------------------------------------------------------------
     |  Properties
     | -----------------------------------------------------------------
     */

    /**
     * The log viewer instance
     *
     * @var \Arcanedev\LogViewer\Contracts\LogViewer
     */
    protected $logViewer;

    /** @var int */
    protected $perPage = 30;

    /** @var string */
    protected $showRoute = 'log-viewer::logs.show';

    /* -----------------------------------------------------------------
     |  Constructor
     | -----------------------------------------------------------------
     */

    /**
     * LogViewerController constructor.
     *
     * @param  \Arcanedev\LogViewer\Contracts\LogViewer  $logViewer
     */
    public function __construct(LogViewerContract $logViewer)
    {
        $this->logViewer = $logViewer;
        $this->perPage = config('log-viewer.per-page', $this->perPage);
        $this->showRoute = config('log-viewer.route.show', $this->showRoute);
    }

    /* -----------------------------------------------------------------
     |  Main Methods
     | -----------------------------------------------------------------
     */

    /**
     * Show the dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $stats     = $this->logViewer->statsTable();
        $chartData = $this->prepareChartData($stats);
        $percents  = $this->calcPercentages($stats->footer(), $stats->header());

        return $this->view('dashboard', compact('chartData', 'percents'));
    }

    /**
     * List all logs.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\View\View
     */
    public function listLogs(Request $request)
    {
        $stats      = $this->logViewer->statsTable();
        $headers    = $stats->header();
        $rows       = $this->paginate($stats->rows(), $request, $this->resolvePerPage($request));
        $datedCount = count($this->logViewer->datedDates());
        $extraCount = count($this->logViewer->extraDates());

        return $this->view('logs', compact('headers', 'rows', 'datedCount', 'extraCount'));
    }

    /**
     * Show the log.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string                    $date
     *
     * @return \Illuminate\View\View
     */
    public function show(Request $request, $date)
    {
        $level   = 'all';
        $log     = $this->getLogOrFail($date);
        $query   = $request->get('query');
        $levels  = $this->logViewer->levelsNames();
        $entries = $log->entries($level)->paginate($this->resolvePerPage($request));

        return $this->view('show', compact('level', 'log', 'query', 'levels', 'entries'));
    }

    /**
     * Filter the log entries by level.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string                    $date
     * @param  string                    $level
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showByLevel(Request $request, $date, $level)
    {
        if ($level === 'all')
            return redirect()->route($this->showRoute, [$date]);

        $log     = $this->getLogOrFail($date);
        $query   = $request->get('query');
        $levels  = $this->logViewer->levelsNames();
        $entries = $this->logViewer->entries($date, $level)->paginate($this->resolvePerPage($request));

        return $this->view('show', compact('level', 'log', 'query', 'levels', 'entries'));
    }

    /**
     * Show the log with the search query.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string                    $date
     * @param  string                    $level
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function search(Request $request, $date, $level = 'all')
    {
        $query   = $request->get('query');

        if (is_null($query))
            return redirect()->route($this->showRoute, [$date]);

        $log     = $this->getLogOrFail($date);
        $levels  = $this->logViewer->levelsNames();
        $needles = array_map(function ($needle) {
            return Str::lower($needle);
        }, array_filter(explode(' ', $query)));
        $entries = $log->entries($level)
            ->unless(empty($needles), function (LogEntryCollection $entries) use ($needles) {
                return $entries->filter(function (LogEntry $entry) use ($needles) {
                    foreach ([$entry->header, $entry->stack, $entry->context()] as $subject) {
                        if (Str::containsAll(Str::lower($subject), $needles))
                            return true;
                    }

                    return false;
                });
            })
            ->paginate($this->resolvePerPage($request));

        return $this->view('show', compact('level', 'log', 'query', 'levels', 'entries'));
    }

    /**
     * Download the log
     *
     * @param  string  $date
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download($date)
    {
        return $this->logViewer->download($date);
    }

    /**
     * Delete a log.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        abort_unless($request->ajax(), 405, 'Method Not Allowed');

        $date = $request->input('date');

        return response()->json([
            'result' => $this->logViewer->delete($date) ? 'success' : 'error'
        ]);
    }

    /**
     * Delete all the dated logs currently listed (e.g. `laravel-2024-01-01.log`).
     * Extra/non date-based logs (configured via `log-viewer.pattern.extra`,
     * such as `worker.log`) are only included when the request explicitly
     * asks for it (`include_extra`); otherwise delete them individually.
     * The active log file of the current day is kept, same as the
     * single-delete action.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAll(Request $request)
    {
        abort_unless($request->ajax(), 405, 'Method Not Allowed');

        $today   = now()->toDateString();
        $dates   = $this->logViewer->datedDates();

        if ($request->boolean('include_extra')) {
            $dates = array_merge($dates, $this->logViewer->extraDates());
        }

        $deleted = [];
        $failed  = [];

        foreach ($dates as $date) {
            if ($date === $today) {
                continue;
            }

            try {
                if ($this->logViewer->delete($date)) {
                    $deleted[] = $date;
                }
                else {
                    $failed[] = $date;
                }
            }
            catch (\Throwable $e) {
                $failed[] = $date;
            }
        }

        return response()->json([
            'result'  => empty($failed) ? 'success' : 'error',
            'deleted' => $deleted,
            'failed'  => $failed,
        ]);
    }

    /**
     * Empty the contents of a log file without deleting it.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clear(Request $request)
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];

        file_put_contents($this->getLogOrFail($date)->getPath(), '');

        return redirect()->back(fallback: route('log-viewer::logs.list'));
    }

    /**
     * Serve a compiled asset (css, js, webfonts) shipped with the package.
     *
     * @param  string  $path
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function asset(string $path)
    {
        $file = realpath(dirname(__DIR__, 3).'/dist/'.$path);

        abort_unless(
            preg_match('#^(css|js|webfonts)/[\w.-]+$#', $path) === 1 && $file !== false && is_file($file),
            404
        );

        $types = ['css' => 'text/css', 'js' => 'text/javascript', 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf'];

        return response()->file($file, [
            'Content-Type'  => $types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    /* -----------------------------------------------------------------
     |  Other Methods
     | -----------------------------------------------------------------
     */

    /**
     * Get the evaluated view contents for the given view.
     *
     * @param  string  $view
     * @param  array   $data
     * @param  array   $mergeData
     *
     * @return \Illuminate\View\View
     */
    protected function view($view, $data = [], $mergeData = [])
    {
        $theme = config('log-viewer.theme');

        return view()->make("log-viewer::{$theme}.{$view}", $data, $mergeData);
    }

    /**
     * Paginate logs.
     *
     * @param  array                     $data
     * @param  \Illuminate\Http\Request  $request
     * @param  int|null                  $perPage
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    protected function paginate(array $data, Request $request, $perPage = null)
    {
        $data    = new Collection($data);
        $page    = $request->get('page', 1);
        $path    = $request->url();
        $perPage = $perPage ?? $this->perPage;

        return new LengthAwarePaginator(
            $data->forPage($page, $perPage),
            $data->count(),
            $perPage,
            $page,
            compact('path')
        );
    }

    /**
     * Resolve the per-page value from the request (e.g. the pagination
     * view's "items per page" selector), falling back to the configured
     * default. Clamped to a sane range to avoid pulling an excessive
     * number of log entries into memory at once.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return int
     */
    protected function resolvePerPage(Request $request)
    {
        $perPage = (int) $request->get('per_page', $this->perPage);

        if ($perPage < 1) {
            return $this->perPage;
        }

        return min($perPage, 1000);
    }

    /**
     * Get a log or fail
     *
     * @param  string  $date
     *
     * @return \Arcanedev\LogViewer\Entities\Log|null
     */
    protected function getLogOrFail($date)
    {
        $log = null;

        try {
            $log = $this->logViewer->get($date);
        }
        catch (LogNotFoundException $e) {
            abort(404, $e->getMessage());
        }

        return $log;
    }

    /**
     * Prepare chart data.
     *
     * @param  \Arcanedev\LogViewer\Tables\StatsTable  $stats
     *
     * @return string
     */
    protected function prepareChartData(StatsTable $stats)
    {
        $totals = $stats->totals()->all();

        return json_encode([
            'labels'   => Arr::pluck($totals, 'label'),
            'datasets' => [
                [
                    'data'                 => Arr::pluck($totals, 'value'),
                    'backgroundColor'      => Arr::pluck($totals, 'color'),
                    'hoverBackgroundColor' => Arr::pluck($totals, 'highlight'),
                ],
            ],
        ]);
    }

    /**
     * Calculate the percentage.
     *
     * @param  array  $total
     * @param  array  $names
     *
     * @return array
     */
    protected function calcPercentages(array $total, array $names)
    {
        $percents = [];
        $all      = Arr::get($total, 'all');

        foreach ($total as $level => $count) {
            $percents[$level] = [
                'name'    => $names[$level],
                'count'   => $count,
                'percent' => $all ? round(($count / $all) * 100, 2) : 0,
            ];
        }

        return $percents;
    }
}
