<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="wrap">
        <header>
            <h1>{{ config('app.name') }}<span class="env">{{ app()->environment() }}</span></h1>
            <p class="lede">API backend. This page is the operations overview &mdash; the application frontend is a separate project.</p>
        </header>

        <div class="cards">
            <a class="card" href="{{ route('scramble.docs.ui') }}">
                <h2>API documentation</h2>
                <p>OpenAPI reference, generated from the code by Scramble.</p>
            </a>

            <a class="card" href="{{ route('horizon.index') }}">
                <h2>Horizon</h2>
                <p>Queue workers, throughput, failed jobs and retries.</p>
            </a>

            <a class="card" href="{{ url(config('pulse.path')) }}">
                <h2>Pulse</h2>
                <p>Application performance: slow queries, exceptions, usage.</p>
            </a>
        </div>

        <footer>
            Laravel {{ app()->version() }} &middot; PHP {{ PHP_VERSION }} &middot; served from <code>public/css/app.css</code>, no build step.
        </footer>
    </div>
</body>
</html>
