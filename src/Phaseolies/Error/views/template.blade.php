<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Error - {{ $error_message }}</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <script>
        const loadDarkMode = () => {
            const storedTheme = localStorage.getItem('theme');
            const theme = storedTheme !== null ? storedTheme : 'system';

            if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        }

        loadDarkMode();
    </script>
</head>

<style type="text/tailwindcss">
    @theme {
        --color-primary: --color-neutral-50;
        --color-primary-fg: --color-neutral-50;

        /*  Highlighting Colors - Light Mode */
        --color-hl-tag: #6c7086;
        --color-hl-variable: #fe640b;
        --color-hl-string: #7287fd;
        --color-hl-definition: #8839ef;
        --color-hl-modifier: #df8e1d;
        --color-hl-keyword: #d20f39;
        --color-hl-literal: #40a02b;
        --color-hl-comment: #9ca0b0;
        --color-hl-number: #fe640b;
        --color-hl-default: #4c4f69;
    }

    @layer theme {
        .dark {
            /* Highlighting Colors - Dark Mode */
            --color-hl-tag: #565f89;
            --color-hl-variable: #ff9e64;
            --color-hl-string: #9ece6a;
            --color-hl-definition: #7aa2f7;
            --color-hl-modifier: #bb9af7;
            --color-hl-keyword: #f7768e;
            --color-hl-literal: #9ece6a;
            --color-hl-comment: #565f89;
            --color-hl-number: #ff9e64;
            --color-hl-default: #c0caf5;
        }
    }

    @custom-variant dark (&:where(.dark, .dark *));

    @layer components {
        .badge {
            @apply px-2 py-1 rounded font-medium transition-all duration-200;
        }

        .badge[data-request-type="GET"] {
            @apply bg-green-500/10 text-green-600 dark:text-green-400 border border-green-500/20;
        }

        .badge[data-request-type="POST"] {
            @apply bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20;
        }

        .badge[data-request-type="PUT"] {
            @apply bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 border border-yellow-500/20;
        }

        .badge[data-request-type="DELETE"] {
            @apply bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20;
        }

        /* Code Line Components */
        .code-line {
            @apply inline-flex w-full transition-colors duration-150;
        }

        .code-line-error {
            @apply inline-flex my-1 w-full bg-red-500/10 border-l-4 border-l-red-500 py-0.5 ;
            animation: pulse-slow 2s ease-in-out infinite;
        }

        @keyframes pulse-slow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        .code-line-number {
            @apply w-12 text-right pr-3 text-neutral-500 select-none shrink-0;
        }

        .code-line-content {
            @apply flex-1;
        }

        .info-card {
            @apply rounded-lg border-[1.2px] dark:bg-neutral-950/30 bg-neutral-50/50 backdrop-blur-sm dark:border-white/5 border-neutral-900/5 p-4 transition-all duration-200 ;
        }

        .info-label {
            @apply text-xs text-neutral-600 dark:text-neutral-400 font-medium mb-1 uppercase tracking-wider;
        }

        .info-value {
            @apply text-neutral-900 dark:text-neutral-100 font-medium;
        }
    }
</style>

<body
    class="px-2 antialiased tracking-wide md:px-3 lg:px-12 py-2 md:py-3 lg:py-6 min-h-screen bg-gradient-to-br from-neutral-50 via-neutral-100 to-neutral-200 dark:from-neutral-950 dark:via-neutral-900 dark:to-neutral-950 text-neutral-900 dark:text-neutral-50 transition-colors duration-200">
    
    {{-- Top Bar --}}
    <div class="top-bar rounded-lg mb-6 group">
        <div class="relative overflow-hidden border-[1.2px] border-dashed dark:bg-gradient-to-br dark:from-red-500/5 dark:to-transparent bg-gradient-to-br from-red-500/3 to-transparent dark:border-red-500/20 border-red-500/30 p-6 rounded-lg transition-all duration-300 shadow-xl shadow-red-500/5 ">
            {{-- niice background blur --}}
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-red-500/20 rounded-full blur-3xl opacity-50"></div>
            
            <div class="flex flex-col md:flex-row md:items-start gap-4 relative z-10">
                {{-- Error Icon --}}
                <div class="flex-shrink-0 p-3 rounded-xl bg-red-500/10 dark:bg-red-500/20 border border-red-500/20  transition-transform duration-300">
                    <svg class="size-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-bold text-red-600 dark:text-red-400 mb-2 tracking-wide uppercase">
                        {{ $exception_class }}
                    </div>
                    <h1 class="text-2xl md:text-3xl font-semibold text-neutral-900 dark:text-white mb-1 break-words leading-tight">
                        {{ $error_message }}
                    </h1>
                </div>

                <div class="flex flex-col items-end gap-3">
                    <div class="flex gap-2">
                        <button id="themeToggle" class="p-2.5 cursor-pointer rounded-lg hover:bg-neutral-100 dark:hover:bg-white/10 transition-all duration-200 hover:scale-110 active:scale-95 border border-neutral-200 dark:border-white/10" aria-label="Toggle theme">
                            <svg id="sunIcon" class="hidden dark:block size-5" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                            </svg>
                            <svg id="moonIcon" class="block dark:hidden size-5" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                            </svg>
                        </button>
                        <button id="copyToClipBoard" class="p-2.5 cursor-pointer rounded-lg hover:bg-neutral-100 dark:hover:bg-white/10 transition-all duration-200 hover:scale-110 active:scale-95 border border-neutral-200 dark:border-white/10" title="Copy as markdown">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                            </svg>
                        </button>
                    </div>
                    {{-- Versions --}}
                    <div class="rounded-lg flex border-[1.2px] border-dashed dark:bg-white/[2%] bg-neutral-900/[2%] text-sm py-1.5 border-neutral-900/10 px-3 dark:border-white/10">
                        <div class="pl-1 pr-3 border-r border-neutral-900/10 dark:border-white/10">
                            <span class="text-neutral-600 dark:text-neutral-500 pr-2 text-xs">DOPPAR</span>
                            <span class="font-semibold">{{ $doppar_version }}</span>
                        </div>
                        <div class="px-3">
                            <span class="text-neutral-600 dark:text-neutral-500 pr-2 text-xs">PHP</span>
                            <span class="font-semibold">{{ $php_version }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Request Details --}}
    <div class="flex flex-wrap gap-3 items-center border-[1.2px] border-dashed dark:bg-white/[2%] bg-neutral-50/50 backdrop-blur-sm my-5 dark:border-white/10 border-neutral-900/10 p-3 rounded-lg hover:shadow-lg transition-all duration-200">
        <span data-request-type="{{ $request_method }}" class="badge px-3 py-1.5 rounded-md font-semibold">
            {{ $request_method }}
        </span>
        <span class="dark:text-neutral-300 text-neutral-700 font-mono text-sm flex-1 min-w-0 truncate">{{ $request_url }}</span>
        <div class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            {{ $timestamp }}
        </div>
    </div>

    {{-- Main Content --}}
    <div class="flex flex-col gap-5">
        <main class="rounded-lg border-[1.2px] border-dashed w-full dark:bg-white/[2%] bg-neutral-50/50 backdrop-blur-sm border-neutral-900/10 dark:border-white/10 p-4 hover:shadow-xl transition-all duration-300">
            {{-- File Header --}}
            <div class="flex items-center gap-3 bg-gradient-to-r from-neutral-100 to-neutral-50 dark:from-white/7 dark:to-white/5 rounded-lg px-4 py-3 mb-4 border border-neutral-200 dark:border-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-6 ">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m6.75 7.5 3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                <span class="font-mono text-sm">{{ $error_file }}</span>
            </div>

            {{-- Code Content --}}
            <div class="rounded-lg overflow-hidden border border-neutral-200 dark:border-white/10">
                <pre class="[overflow-x-auto p-4">{!! $contents !!}</pre>
            </div>

            <div class="mt-8">
                <div class="flex items-center justify-between mb-4 px-2">
                    <h3 class="text-lg font-bold flex items-center gap-3">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        Stack Trace
                    </h3>
                    <button id="toggleAllFramesBtn"
                        class="text-sm px-4 py-2 rounded-lg cursor-pointer hover:scale-105 transition-all duration-200 bg-neutral-100 dark:bg-white/5 border border-dashed dark:border-white/10 border-neutral-900/10 font-medium hover:bg-neutral-200 dark:hover:bg-white/10">
                        <span id="toggleAllText">Expand All</span>
                    </button>
                </div>
                <div id="traceFrames">
                    @include('trace-frames', ['traces' => $traces])
                </div>
            </div>
        </main>

        <div id="headers" class="my-2">
            @include('template-headers', ['headers' => $headers])
        </div>
    </div>

    {{-- System & User Info Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 my-6">
        {{-- System Information --}}
        <div class="info-card">
            <div class="flex items-center gap-2 mb-4">
                <svg class="size-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z">
                    </path>
                </svg>
                <h3 class="font-bold text-base">System</h3>
            </div>
            <div class="space-y-3 text-sm">
                <div>
                    <div class="info-label">Server</div>
                    <div class="info-value truncate">{{ $server_software }}</div>
                </div>
                <div>
                    <div class="info-label">Platform</div>
                    <div class="info-value truncate">{{ $platform }}</div>
                </div>
            </div>
        </div>

        {{-- Memory Usage --}}
        <div class="info-card">
            <div class="flex items-center gap-2 mb-4">
                <svg class="size-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                <h3 class="font-bold text-base">Memory</h3>
            </div>
            <div class="space-y-3 text-sm">
                <div>
                    <div class="info-label">Current Usage</div>
                    <div class="info-value">{{ number_format($memory_usage / 1024 / 1024, 2) }} MB</div>
                </div>
                <div>
                    <div class="info-label">Peak Usage</div>
                    <div class="info-value">{{ number_format($peack_memory_usage / 1024 / 1024, 2) }} MB</div>
                </div>
            </div>
        </div>

        {{-- User Information --}}
        <div class="info-card">
            <div class="flex items-center gap-2 mb-4">
                <svg class="size-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <h3 class="font-bold text-base">User</h3>
            </div>
            @if ($user_info)
                <div class="space-y-3 text-sm">
                    <div>
                        <div class="info-label">ID</div>
                        <div class="info-value">{{ $user_info['id'] }}</div>
                    </div>
                    <div>
                        <div class="info-label">Email</div>
                        <div class="info-value truncate">{{ $user_info['email'] }}</div>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-4 text-neutral-500 dark:text-neutral-400">
                    <svg class="w-8 h-8 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                    </svg>
                    <p class="text-sm font-mono">// NO USER</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Request Body --}}
    <div class="info-card mb-5">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <svg class="size-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                    </path>
                </svg>
                <h3 class="font-bold text-base">Request Body</h3>
            </div>
            @if (!empty($request_body))
                <button
                    class="accordion-header text-sm px-3 py-1.5 rounded-md bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-all duration-200 border border-neutral-200 dark:border-white/10">
                    <span class="accordion-arrow inline-block transition-transform duration-200">▼</span>
                </button>
            @endif
        </div>
        @if (!empty($request_body))
            <div class="accordion-content hidden">
                <pre class="text-sm bg-neutral-100 dark:bg-white/5 rounded-lg p-4 overflow-x-auto border border-neutral-200 dark:border-white/10"><code>{{ json_encode($request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-12 text-neutral-400 dark:text-neutral-600">
                <svg class="w-16 h-16 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                    </path>
                </svg>
                <p class="text-base font-mono font-semibold">// EMPTY REQUEST BODY</p>
            </div>
        @endif
    </div>

    {{-- Routing Details --}}
    <div class="info-card mb-5">
        <div class="flex items-center gap-2 mb-4">
            <svg class="size-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
            </svg>
            <h3 class="font-bold text-base">Routing</h3>
        </div>

        {{-- Controller & Middleware Info --}}
        {{-- @if (!empty($routing['controller']) || !empty($routing['middleware']))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                @if (!empty($routing['controller']))
                    <div class="bg-neutral-100 dark:bg-white/5 rounded-lg p-3 border border-neutral-200 dark:border-white/10">
                        <div class="info-label">Controller</div>
                        <div class="font-mono text-sm break-all font-medium">{{ $routing['controller'] }}</div>
                    </div>
                @endif

                @if (!empty($routing['middleware']))
                    <div class="bg-neutral-100 dark:bg-white/5 rounded-lg p-3 border border-neutral-200 dark:border-white/10">
                        <div class="info-label">Middleware</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($routing['middleware'] as $mw)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-mono bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 font-semibold">{{ $mw }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif --}}

        {{-- Route Parameters --}}
        <div>
            <div class="info-label mb-3">Route Parameters</div>
            @if (!empty($routing['params']))
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach ($routing['params'] as $key => $value)
                        <div class="bg-neutral-100 dark:bg-white/5 rounded-lg p-3 border border-neutral-200 dark:border-white/10">
                            <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">{{ $key }}</div>
                            <div class="font-mono text-sm text-neutral-900 dark:text-neutral-100 font-semibold">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-8 text-neutral-400 dark:text-neutral-600">
                    <svg class="w-12 h-12 mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7">
                        </path>
                    </svg>
                    <p class="text-base font-mono font-semibold">// NO ROUTE PARAMETERS</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Hidden markdown content for clipboard --}}
    <textarea id="mdContent" class="hidden">{{ $md_content }}</textarea>
</body>

<script>
    const ThemeManager = {
        getTheme() {
            const stored = localStorage.getItem('theme');
            if (stored) return stored;
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        },

        applyTheme(theme) {
            const html = document.documentElement;
            if (theme === 'dark') {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
            localStorage.setItem('theme', theme);
        },

        toggleTheme() {
            const current = this.getTheme();
            const newTheme = current === 'dark' ? 'light' : 'dark';
            this.applyTheme(newTheme);
            return newTheme;
        },

        init() {
            this.applyTheme(this.getTheme());
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (!localStorage.getItem('theme')) {
                    this.applyTheme(e.matches ? 'dark' : 'light');
                }
            });

            const toggleBtn = document.getElementById('themeToggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => {
                    this.toggleTheme();
                });
            }
        }
    };

    ThemeManager.init();

    document.getElementById('copyToClipBoard')?.addEventListener('click', async function() {
        const mdContent = document.getElementById('mdContent')?.value;
        if (!mdContent) return;

        try {
            await navigator.clipboard.writeText(mdContent);

            // Visual feedback with success animation
            const btn = this;

            const originalHTML = btn.innerHTML;
            
            btn.innerHTML =
                '<svg class="size-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
            btn.classList.add('bg-green-500/10');

            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.classList.remove('bg-green-500/10');
            }, 2000);

        } catch (err) {
            console.error('Failed to copy:', err);
            // Show error feedback
            const btn = this;

            btn.classList.add('bg-red-500/10');
            
            setTimeout(() => {
                btn.classList.remove('bg-red-500/10');
            }, 1000);
        }
    });
</script>

<script>
    function setupAccordion(containerSelector, options = {}) {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const headerSelector = options.headerSelector || '.accordion-header';
        const contentSelector = options.contentSelector || '.accordion-content';
        const arrowSelector = options.arrowSelector || '.accordion-arrow';
        const toggleAllBtnSelector = options.toggleAllBtnSelector;

        const headers = container.querySelectorAll(headerSelector);

        headers.forEach(header => {
            header.setAttribute('tabindex', '0');
            header.setAttribute('role', 'button');

            const content = header.nextElementSibling;
            if (!content) return;

            const openByDefault =
                container.dataset.openByDefault !== undefined ||
                header.dataset.openByDefault !== undefined;

            if (openByDefault) {
                content.classList.remove('hidden');
                header.setAttribute('aria-expanded', 'true');
                const arrow = header.querySelector(arrowSelector);
                if (arrow) arrow.style.transform = 'rotate(180deg)';
            } else {
                header.setAttribute('aria-expanded', 'false');
            }

            header.addEventListener('click', () => toggleSection(header));
            header.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleSection(header);
                }
            });
        });

        function toggleSection(header) {
            const content = header.nextElementSibling;
            if (!content) return;

            const arrow = header.querySelector(arrowSelector);
            const isExpanded = !content.classList.contains('hidden');

            content.classList.toggle('hidden');
            header.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
            if (arrow) arrow.style.transform = isExpanded ? 'rotate(0deg)' : 'rotate(180deg)';
        }

        if (toggleAllBtnSelector) {
            const toggleAllBtn = document.querySelector(toggleAllBtnSelector);
            if (!toggleAllBtn) return;

            let allExpanded = false;

            toggleAllBtn.addEventListener('click', () => {
                allExpanded = !allExpanded;

                headers.forEach(header => {
                    const content = header.nextElementSibling;
                    const arrow = header.querySelector(arrowSelector);
                    if (!content || !arrow) return;

                    if (allExpanded) {
                        content.classList.remove('hidden');
                        header.setAttribute('aria-expanded', 'true');
                        arrow.style.transform = 'rotate(180deg)';
                    } else {
                        content.classList.add('hidden');
                        header.setAttribute('aria-expanded', 'false');
                        arrow.style.transform = 'rotate(0deg)';
                    }
                });

                const text = document.getElementById('toggleAllText');
                if (text) {
                    text.textContent = allExpanded ? 'Collapse All' : 'Expand All';
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupAccordion('#single-accordion-container');

        setupAccordion('body', {
            headerSelector: '.frame-header',
            contentSelector: '.frame-content',
            arrowSelector: '.frame-arrow',
            toggleAllBtnSelector: '#toggleAllFramesBtn',
        });
    });
</script>

</html>