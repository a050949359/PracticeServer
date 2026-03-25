<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="app-locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
        <meta name="app-fallback-locale" content="{{ str_replace('_', '-', config('app.fallback_locale')) }}">
        <title>{{ config('app.name', 'Laravel') }}</title>
        @php
            $hasViteAssets = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
            $viteEntry = request()->is('admin*')
                ? 'resources/js/admin.js'
                : (request()->is('register*') ? 'resources/js/register.js' : 'resources/js/public.js');
        @endphp

        @if ($hasViteAssets)
            @vite([$viteEntry])
        @else
            <style>
                body {
                    margin: 0;
                    font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Noto Sans, Ubuntu, Cantarell, Helvetica Neue, Arial;
                    background: #020617;
                    color: #e2e8f0;
                }

                .spa-topbar {
                    position: sticky;
                    top: 0;
                    z-index: 40;
                    background: rgba(2, 6, 23, 0.82);
                    backdrop-filter: blur(10px);
                    border-bottom: 1px solid rgba(148, 163, 184, 0.22);
                }

                .spa-topbar-inner {
                    max-width: 72rem;
                    margin: 0 auto;
                    height: 4rem;
                    padding: 0 1rem;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }

                .spa-brand {
                    color: #f8fafc;
                    text-decoration: none;
                    font-weight: 700;
                }

                .spa-actions {
                    display: flex;
                    gap: 0.5rem;
                }

                .spa-btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 0.6rem;
                    padding: 0.45rem 0.85rem;
                    font-size: 0.875rem;
                    text-decoration: none;
                    border: 1px solid transparent;
                }

                .spa-btn-ghost {
                    color: #e2e8f0;
                    border-color: rgba(148, 163, 184, 0.35);
                    background: rgba(15, 23, 42, 0.4);
                }

                .spa-btn-primary {
                    color: #082f49;
                    background: #22d3ee;
                    border-color: #22d3ee;
                    font-weight: 600;
                }

                .shell {
                    min-height: calc(100vh - 4rem);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 2rem;
                }

                .card {
                    max-width: 56rem;
                    width: 100%;
                }

                .badge {
                    display: inline-block;
                    padding: 0.25rem 0.75rem;
                    border-radius: 999px;
                    background: rgba(34, 211, 238, 0.2);
                    color: #a5f3fc;
                    font-size: 0.875rem;
                }

                h1 {
                    margin: 1rem 0 0.75rem;
                    font-size: 2rem;
                    line-height: 1.2;
                }

                p {
                    margin: 0.5rem 0;
                    color: #cbd5e1;
                    line-height: 1.7;
                }
            </style>
        @endif
    </head>
    <body>
        @if (! $hasViteAssets)
            <header class="spa-topbar">
                <div class="spa-topbar-inner">
                    <a href="/" class="spa-brand">{{ config('app.name', 'Laravel') }}</a>
                    <nav class="spa-actions" aria-label="auth actions">
                        <a href="/login" class="spa-btn spa-btn-ghost">登入</a>
                        <a href="/register" class="spa-btn spa-btn-primary">註冊</a>
                    </nav>
                </div>
            </header>
        @endif

        <div id="app"></div>
        @if (! $hasViteAssets)
            <script>
                const app = document.querySelector('#app');

                if (app) {
                    app.innerHTML = `
                        <main class="shell">
                            <section class="card">
                                <span class="badge">CSR Mode</span>
                                <h1>這是前端渲染頁面</h1>
                                <p>目前沒有載入 Vite 資產，所以使用 fallback 的瀏覽器端渲染。</p>
                                <p>啟動 <strong>npm run dev</strong> 或執行 <strong>npm run build</strong> 後，會改用對應區域的正式前端入口。</p>
                            </section>
                        </main>
                    `;
                }
            </script>
        @endif
    </body>
</html>