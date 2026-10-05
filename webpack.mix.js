const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Log Viewer assets
 |--------------------------------------------------------------------------
 |
 | Compiles the assets into dist/, which is committed and served by the
 | package itself (route "log-viewer::assets"), so projects never compile.
 |
 */

mix.setPublicPath('dist')
    .js('resources/js/log-viewer.js', 'js')
    .sass('resources/sass/log-viewer.scss', 'css')
    .copy('node_modules/@fortawesome/fontawesome-free/webfonts/*.{woff2,woff,ttf}', 'dist/webfonts')
    .options({ processCssUrls: false });
