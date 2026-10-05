<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="LogViewer">
    <meta name="author" content="buriti8 (fork of ARCANEDEV/LogViewer)">
    <title>{{ config('app.name')}} | Log</title>
    @if (config('log-viewer.favicon'))
    <link rel="icon" href="{{ asset(config('log-viewer.favicon')) }}">
    @endif
    {{-- Styles --}}
    <link href="{{ log_viewer_asset('css/log-viewer.css') }}" rel="stylesheet">
    <style>
        /*
         * Colors: Badge & Infobox
         */

        .badge.badge-env,
        .badge.badge-level-all,
        .badge.badge-level-emergency,
        .badge.badge-level-alert,
        .badge.badge-level-critical,
        .badge.badge-level-error,
        .badge.badge-level-warning,
        .badge.badge-level-notice,
        .badge.badge-level-info,
        .badge.badge-level-debug,
        .badge.empty {
            color: #FFF;
            text-shadow: 0 1px 1px rgba(0, 0, 0, 0.3);
        }

        .badge.badge-level-all,
        .box.level-all {
            background-color: {{ log_styler()->color('all') }};
        }

        .badge.badge-level-emergency,
        .box.level-emergency {
            background-color: {{ log_styler()->color('emergency') }};
        }

        .badge.badge-level-alert,
        .box.level-alert  {
            background-color: {{ log_styler()->color('alert') }};
        }

        .badge.badge-level-critical,
        .box.level-critical {
            background-color: {{ log_styler()->color('critical') }};
        }

        .badge.badge-level-error,
        .box.level-error {
            background-color: {{ log_styler()->color('error') }};
        }

        .badge.badge-level-warning,
        .box.level-warning {
            background-color: {{ log_styler()->color('warning') }};
        }

        .badge.badge-level-notice,
        .box.level-notice {
            background-color: {{ log_styler()->color('notice') }};
        }

        .badge.badge-level-info,
        .box.level-info {
            background-color: {{ log_styler()->color('info') }};
        }

        .badge.badge-level-debug,
        .box.level-debug {
            background-color: {{ log_styler()->color('debug') }};
        }

        .badge.empty,
        .box.empty {
            background-color: {{ log_styler()->color('empty') }};
        }

        .badge.badge-env {
            background-color: #6A1B9A;
        }

        #entries {
            overflow-wrap: anywhere;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-dark sticky-top bg-dark p-0">
        <a href="{{ route('log-viewer::dashboard') }}" class="navbar-brand mr-0">
            <i class="fa fa-fw fa-book"></i> {{ config('app.name')}} | Log
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item {{ Route::is('log-viewer::dashboard') ? 'active' : '' }}">
                    <a href="{{ route('log-viewer::dashboard') }}" class="nav-link">
                        <i class="fa fa-dashboard"></i> @lang('Dashboard')
                    </a>
                </li>
                <li class="nav-item {{ Route::is('log-viewer::logs.list') ? 'active' : '' }}">
                    <a href="{{ route('log-viewer::logs.list') }}" class="nav-link">
                        <i class="fa fa-archive"></i> @lang('Logs')
                    </a>
                </li>
                @if (Route::has('pulse'))
                <li class="nav-item">
                    <a href="{{ route('pulse') }}" class="nav-link" target="_blank">
                        <i class="fa fa-heartbeat"></i> @lang('Pulse')
                    </a>
                </li>
                @endif
            </ul>
        </div>
    </nav>

    <div class="container-fluid">
        <main role="main" class="pt-3">
            @yield('content')
        </main>
    </div>

    {{-- Scripts --}}
    <script src="{{ log_viewer_asset('js/log-viewer.js') }}"></script>
    <script>
        function ready(fn) {
            if (document.readyState !== 'loading'){
                fn();
            } else {
                document.addEventListener('DOMContentLoaded', fn);
            }
        }
    </script>

    @yield('modals')
    @yield('scripts')
</body>
</html>
