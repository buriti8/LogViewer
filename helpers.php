<?php

use Arcanedev\LogViewer\Contracts;

if ( ! function_exists('log_viewer')) {
    /**
     * Get the LogViewer instance.
     *
     * @return Arcanedev\LogViewer\Contracts\LogViewer
     */
    function log_viewer()
    {
        return app(Contracts\LogViewer::class);
    }
}

if ( ! function_exists('log_levels')) {
    /**
     * Get the LogLevels instance.
     *
     * @return Arcanedev\LogViewer\Contracts\Utilities\LogLevels
     */
    function log_levels()
    {
        return app(Contracts\Utilities\LogLevels::class);
    }
}

if ( ! function_exists('log_menu')) {
    /**
     * Get the LogMenu instance.
     *
     * @return Arcanedev\LogViewer\Contracts\Utilities\LogMenu
     */
    function log_menu()
    {
        return app(Contracts\Utilities\LogMenu::class);
    }
}

if ( ! function_exists('log_styler')) {
    /**
     * Get the LogStyler instance.
     *
     * @return Arcanedev\LogViewer\Contracts\Utilities\LogStyler
     */
    function log_styler()
    {
        return app(Contracts\Utilities\LogStyler::class);
    }
}

if ( ! function_exists('log_viewer_asset')) {
    /**
     * Get the URL of an asset compiled and served by the package.
     *
     * @param  string  $path
     *
     * @return string
     */
    function log_viewer_asset(string $path): string
    {
        $file    = __DIR__.'/dist/'.$path;
        $version = is_file($file) ? filemtime($file) : 0;

        return route('log-viewer::assets', ['path' => $path]).'?v='.$version;
    }
}
