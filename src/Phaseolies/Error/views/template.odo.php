<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Error — [[ $error_message ]]</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@300;400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <script>
        (() => {
            const stored = localStorage.getItem('theme');
            const theme = stored !== null ? stored : 'system';
            if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <style type="text/tailwindcss">
        @theme {
            --font-mono: 'Geist Mono', monospace;
            --font-serif: 'Instrument Serif', serif;
            --color-hl-tag: #94a3b8;
            --color-hl-variable: #f97316;
            --color-hl-string: #6366f1;
            --color-hl-definition: #8b5cf6;
            --color-hl-modifier: #d97706;
            --color-hl-keyword: #e11d48;
            --color-hl-literal: #16a34a;
            --color-hl-comment: #94a3b8;
            --color-hl-number: #f97316;
            --color-hl-default: #1e293b;
        }
        @layer theme {
            .dark {
                --color-hl-tag: #475569;
                --color-hl-variable: #fb923c;
                --color-hl-string: #818cf8;
                --color-hl-definition: #a78bfa;
                --color-hl-modifier: #fbbf24;
                --color-hl-keyword: #fb7185;
                --color-hl-literal: #4ade80;
                --color-hl-comment: #475569;
                --color-hl-number: #fb923c;
                --color-hl-default: #e2e8f0;
            }
        }
        @custom-variant dark (&:where(.dark, .dark *));
        * { font-family: 'Geist Mono', monospace; }
        @layer components {
            .badge { @apply inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold tracking-widest uppercase; }
            .badge[data-request-type="GET"]    { @apply bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400; }
            .badge[data-request-type="POST"]   { @apply bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400; }
            .badge[data-request-type="PUT"]    { @apply bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400; }
            .badge[data-request-type="DELETE"] { @apply bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400; }
            .badge[data-request-type="PATCH"]  { @apply bg-purple-100 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400; }
            .code-line        { @apply flex w-full text-xs leading-none; }
            .code-line-error  { @apply flex w-full text-xs leading-none bg-rose-500/8 border-l-2 border-rose-500; }
            .code-line-number  { @apply w-10 text-right pr-4 select-none shrink-0 text-slate-400 dark:text-slate-600; }
            .code-line-content { @apply flex-1 pr-4; }
            .glass-card  { @apply rounded-2xl border border-black/5 dark:border-white/5 bg-white/80 dark:bg-white/3 backdrop-blur-sm; }
            .section-label { @apply text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400 dark:text-slate-600; }
            .mw-chip {
                @apply inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold
                       bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400
                       border border-indigo-100 dark:border-indigo-500/20;
            }
        }
    </style>

    <style>
        body {
            background-color: #f9f9f7;
            background-image: radial-gradient(circle, rgba(0, 0, 0, 0.13) 1px, transparent 1px);
            background-size: 22px 22px;
        }

        html.dark body {
            background-color: #0d0d10;
            background-image: radial-gradient(circle, rgba(255, 255, 255, 0.07) 1px, transparent 1px);
            background-size: 22px 22px;
        }

        body>* {
            position: relative;
            z-index: 1;
        }

        /* ── Scrollbars ── */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, .12);
            border-radius: 99px;
        }

        .dark ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, .08);
        }

        /* ── Error line pulse ── */
        @keyframes pulse-error {

            0%,
            100% {
                background-color: rgba(239, 68, 68, 0.08);
            }

            50% {
                background-color: rgba(239, 68, 68, 0.15);
            }
        }

        .code-line-error {
            animation: pulse-error 2.5s ease-in-out infinite;
            padding: 10px 0;
        }

        /* ── Entrance animations ── */
        @keyframes slide-up {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .anim-1 {
            animation: slide-up .4s ease both;
        }

        .anim-2 {
            animation: slide-up .4s .07s ease both;
        }

        .anim-3 {
            animation: slide-up .4s .14s ease both;
        }

        .anim-4 {
            animation: slide-up .4s .21s ease both;
        }

        .anim-5 {
            animation: slide-up .4s .28s ease both;
        }

        .anim-6 {
            animation: slide-up .4s .35s ease both;
        }

        /* ── Collapsible defaults ── */
        .frame-body {
            display: none;
        }

        .headers-panel {
            display: none;
        }

        .arrow-icon {
            transition: transform 0.2s ease;
        }

        /* ── Clickable rows ── */
        .frame-toggle,
        .headers-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            cursor: pointer;
            user-select: none;
        }

        .frame-toggle:hover {
            background: rgba(0, 0, 0, .02);
        }

        html.dark .frame-toggle:hover {
            background: rgba(255, 255, 255, .02);
        }

        .headers-toggle:hover {
            background: rgba(0, 0, 0, .02);
        }

        html.dark .headers-toggle:hover {
            background: rgba(255, 255, 255, .02);
        }

        /* ── IMPROVED: exception class pill with live dot ── */
        .exception-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px 3px 7px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.01em;
            background: rgba(225, 29, 72, 0.08);
            border: 1px solid rgba(225, 29, 72, 0.18);
            color: #e11d48;
        }

        html.dark .exception-pill {
            background: rgba(251, 113, 133, 0.1);
            border-color: rgba(251, 113, 133, 0.2);
            color: #fb7185;
        }

        .exception-pill-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse-dot 2s ease infinite;
            flex-shrink: 0;
        }

        @keyframes pulse-dot {

            0%,
            100% {
                opacity: .8;
                transform: scale(1);
            }

            50% {
                opacity: .3;
                transform: scale(.6);
            }
        }

        /* ── IMPROVED: subtle hero glow ── */
        .hero-glow {
            position: absolute;
            top: -80px;
            right: -80px;
            width: 500px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(225, 29, 72, 0.06) 0%, transparent 70%);
            pointer-events: none;
        }

        html.dark .hero-glow {
            background: radial-gradient(circle, rgba(251, 113, 133, 0.07) 0%, transparent 70%);
        }

        /* ── IMPROVED: status badge with live dot ── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px 4px 7px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(225, 29, 72, 0.09);
            border: 1px solid rgba(225, 29, 72, 0.2);
            color: #e11d48;
        }

        html.dark .status-badge {
            background: rgba(251, 113, 133, 0.09);
            border-color: rgba(251, 113, 133, 0.2);
            color: #fb7185;
        }

        .status-badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse-dot 1.8s ease infinite;
        }

        /* ── IMPROVED: code line hover ── */
        .code-line:not(.code-line-error):hover {
            background: rgba(0, 0, 0, .025);
        }

        html.dark .code-line:not(.code-line-error):hover {
            background: rgba(255, 255, 255, .02);
        }

        /* ── IMPROVED: param dotted connector ── */
        .param-dot-line {
            flex-grow: 1;
            border-bottom: 1px dashed rgba(0, 0, 0, .1);
            margin: 0 8px 4px;
        }

        html.dark .param-dot-line {
            border-bottom-color: rgba(255, 255, 255, .07);
        }

        /* ── IMPROVED: "Line N" badge in code header ── */
        .line-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.05em;
            background: rgba(225, 29, 72, 0.08);
            border: 1px solid rgba(225, 29, 72, 0.18);
            color: #e11d48;
        }

        html.dark .line-badge {
            background: rgba(251, 113, 133, 0.08);
            border-color: rgba(251, 113, 133, 0.18);
            color: #fb7185;
        }

        /* ── IMPROVED: route live indicator ── */
        .route-live-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #e11d48;
            animation: pulse-dot 1.6s ease infinite;
        }

        html.dark .route-live-dot {
            background: #fb7185;
        }

        /* ── IMPROVED: request bar ── */
        .request-bar {
            border-top: 1px solid rgba(0, 0, 0, .06);
            border-bottom: 1px solid rgba(0, 0, 0, .06);
            background: rgba(255, 255, 255, .4);
            backdrop-filter: blur(4px);
        }

        html.dark .request-bar {
            border-top-color: rgba(255, 255, 255, .05);
            border-bottom-color: rgba(255, 255, 255, .05);
            background: rgba(255, 255, 255, .02);
        }

        /* ── IMPROVED: frame count badge ── */
        .frame-count-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: 700;
            background: rgba(0, 0, 0, .05);
            border: 1px solid rgba(0, 0, 0, .07);
            color: #64748b;
        }

        html.dark .frame-count-badge {
            background: rgba(255, 255, 255, .06);
            border-color: rgba(255, 255, 255, .06);
            color: #94a3b8;
        }
    </style>
</head>

<body class="min-h-screen text-slate-900 dark:text-slate-100">

    <!-- ── HERO BANNER ── -->
    <div class="anim-1 relative overflow-hidden py-8 md:py-10">
        <div class="hero-glow"></div>
        <div class="pointer-events-none absolute -bottom-16 left-1/3 w-64 h-64 rounded-full bg-orange-400/6 blur-3xl"></div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 md:px-8 lg:px-10">

            <!-- Top row: exception pill left, controls right -->
            <div class="flex items-start justify-between gap-4 mb-5 flex-wrap">
                <!-- IMPROVED: pill replaces plain section-label -->
                <div class="exception-pill">
                    <span class="exception-pill-dot"></span>
                    [[ $exception_class ]]
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button id="themeToggle"
                        class="p-2 rounded-xl hover:bg-black/5 dark:hover:bg-white/8 border border-black/8 dark:border-white/8 transition-all cursor-pointer"
                        aria-label="Toggle theme">
                        <svg id="sunIcon" class="hidden dark:block w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                        </svg>
                        <svg id="moonIcon" class="block dark:hidden w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                        </svg>
                    </button>
                    <button id="copyToClipBoard"
                        class="p-2 rounded-xl hover:bg-black/5 dark:hover:bg-white/8 border border-black/8 dark:border-white/8 transition-all cursor-pointer"
                        title="Copy as Markdown">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                        </svg>
                    </button>

                    <div class="flex items-center rounded-xl overflow-hidden border border-black/8 dark:border-white/8 text-xs font-mono">
                        <div class="px-3 py-1.5 bg-black/3 dark:bg-white/3 border-r border-black/8 dark:border-white/8">
                            <span class="text-slate-400 dark:text-slate-600 text-[10px] tracking-widest uppercase mr-1.5">Doppar</span>
                            <span class="font-semibold">[[ $doppar_version ]]</span>
                        </div>
                        <div class="px-3 py-1.5">
                            <span class="text-slate-400 dark:text-slate-600 text-[10px] tracking-widest uppercase mr-1.5">PHP</span>
                            <span class="font-semibold">[[ $php_version ]]</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error message — IMPROVED: italic for serif character -->
            <h1 class="text-xl md:text-2xl lg:text-3xl text-slate-900 dark:text-slate-50 leading-tight mb-4 break-words max-w-4xl"
                style="font-family:'Instrument Serif',serif; font-style:italic;">
                [[ $error_message ]]
            </h1>
        </div>
    </div>

    <!-- ── REQUEST BAR ── -->
    <div class="anim-2 request-bar py-3">
        <div class="max-w-7xl mx-auto px-4 md:px-8 lg:px-10 flex items-center gap-3 flex-wrap">
            <span data-request-type="[[ $request_method ]]" class="badge">[[ $request_method ]]</span>
            <span class="font-mono text-sm text-slate-600 dark:text-slate-400 flex-1 min-w-0 truncate">[[ $request_url ]]</span>
        </div>
    </div>

    <!-- ── OVERVIEW STRIP ── -->
    <div class="anim-2 bg-white/20 dark:bg-black/10">
        <div class="max-w-7xl mx-auto px-4 md:px-8 lg:px-10">
            <div class="flex items-center py-2.5 border-b border-black/5 dark:border-white/5">
                <span class="section-label">Date</span>
                <div class="flex-1"></div>
                <span class="text-xs font-mono text-slate-600 dark:text-slate-400">[[ $timestamp ]]</span>
            </div>
            <div class="flex items-center py-2.5 border-b border-black/5 dark:border-white/5">
                <span class="section-label">Status Code</span>
                <div class="flex-1"></div>
                <!-- IMPROVED: live dot status badge replaces static red badge -->
                <span class="status-badge">
                    <span class="status-badge-dot"></span>
                    [[ $status_code ]]
                </span>
            </div>
            <div class="flex items-center py-2.5">
                <span class="section-label">Method</span>
                <div class="flex-1"></div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-slate-100 dark:bg-white/8 text-slate-600 dark:text-slate-300 border border-black/8 dark:border-white/8">
                    [[ $request_method ]]
                </span>
            </div>
        </div>
    </div>

    <!-- ── MAIN CONTENT ── -->
    <div class="max-w-7xl mx-auto px-4 md:px-8 lg:px-10 py-8 space-y-6">

        <!-- SOURCE FILE VIEWER -->
        <div class="anim-3 glass-card overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-3.5 border-b border-black/5 dark:border-white/5 bg-black/2 dark:bg-white/2">
                <div class="flex gap-1.5">
                    <div class="w-3 h-3 rounded-full bg-rose-400/70"></div>
                    <div class="w-3 h-3 rounded-full bg-amber-400/70"></div>
                    <div class="w-3 h-3 rounded-full bg-emerald-400/70"></div>
                </div>
                <span class="font-mono text-xs text-slate-500 flex-1 truncate">[[ $error_file ]]</span>
                <!-- IMPROVED: rose-tinted line badge instead of plain text -->
                <span class="line-badge shrink-0">Line [[ $error_line ]]</span>
            </div>
            <div class="overflow-x-auto bg-white/60 dark:bg-black/20">
                <pre class="px-4 py-2 text-xs leading-none">[[! $contents !]]</pre>
            </div>
        </div>

        <!-- ── STACK TRACE ── -->
        <div class="anim-4">
            <div class="flex items-center justify-between mb-3 px-1">
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <h2 class="text-sm font-semibold">Stack Trace</h2>
                    <!-- IMPROVED: frame count pill -->
                    <span id="frameCountBadge" class="frame-count-badge"></span>
                </div>
                <button id="toggleAllFramesBtn"
                    class="text-xs px-3 py-1.5 rounded-lg cursor-pointer border border-black/8 dark:border-white/8 hover:bg-black/4 dark:hover:bg-white/4 transition-colors font-medium">
                    <span id="toggleAllText">Expand All</span>
                </button>
            </div>

            <div id="traceFrames" class="glass-card overflow-hidden divide-y divide-black/5 dark:divide-white/5">
                #include('trace-frames', ['traces' => $traces])
            </div>
        </div>

        <!-- ── HEADERS ── -->
        <div class="anim-5 glass-card overflow-hidden">
            #include('template-headers', ['headers' => $headers])
        </div>

        <!-- ── INFO GRID ── -->
        <div class="anim-5 grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- System -->
            <div class="glass-card p-5">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-500/10 border border-violet-100 dark:border-violet-500/20 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                        </svg>
                    </div>
                    <span class="text-sm font-semibold">System</span>
                </div>
                <div class="space-y-3">
                    <div>
                        <div class="section-label mb-1">Server</div>
                        <div class="text-sm font-mono text-slate-700 dark:text-slate-300 truncate">[[ $server_software ]]</div>
                    </div>
                    <div>
                        <div class="section-label mb-1">Platform</div>
                        <div class="text-sm font-mono text-slate-700 dark:text-slate-300 truncate">[[ $platform ]]</div>
                    </div>
                </div>
            </div>

            <!-- Memory -->
            <div class="glass-card p-5">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <span class="text-sm font-semibold">Memory</span>
                </div>
                <div class="space-y-3">
                    <div>
                        <div class="section-label mb-1">Current Usage</div>
                        <div class="text-sm font-mono text-slate-700 dark:text-slate-300">[[ number_format($memory_usage / 1024 / 1024, 2) ]] MB</div>
                    </div>
                    <div>
                        <div class="section-label mb-1">Peak Usage</div>
                        <div class="text-sm font-mono text-slate-700 dark:text-slate-300">[[ number_format($peack_memory_usage / 1024 / 1024, 2) ]] MB</div>
                    </div>
                </div>
            </div>

            <!-- User -->
            <div class="glass-card p-5">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-500/10 border border-blue-100 dark:border-blue-500/20 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <span class="text-sm font-semibold">User</span>
                </div>
                #if ($user_info)
                <div class="space-y-3">
                    <div>
                        <div class="section-label mb-1">ID</div>
                        <div class="text-sm font-mono text-slate-700 dark:text-slate-300">[[ $user_info['id'] ]]</div>
                    </div>
                    <div>
                        <div class="section-label mb-1">Email</div>
                        <div class="text-sm font-mono text-slate-700 dark:text-slate-300 truncate">[[ $user_info['email'] ]]</div>
                    </div>
                </div>
                #else
                <div class="flex flex-col items-center justify-center py-6 text-slate-300 dark:text-slate-700">
                    <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    <p class="text-xs font-mono tracking-widest uppercase">No User</p>
                </div>
                #endif
            </div>
        </div>

        <!-- ── REQUEST BODY ── -->
        <div class="anim-5 glass-card overflow-hidden">
            <!-- IMPROVED: whole header row is clickable with hover state -->
            <div id="reqBodyToggle"
                class="flex items-center gap-3 px-5 py-4 border-b border-black/5 dark:border-white/5 bg-black/2 dark:bg-white/2 cursor-pointer select-none hover:bg-black/3 dark:hover:bg-white/3 transition-colors">
                <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-100 dark:border-amber-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold flex-1">Request Body</span>
                #if (!empty($request_body))
                <svg id="reqBodyArrow" class="arrow-icon w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
                #endif
            </div>
            #if (!empty($request_body))
            <div id="reqBodyPanel" style="display:none;">
                <pre class="text-xs p-5 overflow-x-auto bg-white/60 dark:bg-black/10 leading-relaxed"><code>[[ json_encode($request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ]]</code></pre>
            </div>
            #else
            <div class="flex flex-col items-center justify-center py-12 text-slate-300 dark:text-slate-700">
                <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <p class="text-xs font-mono tracking-widest uppercase">Empty Request Body</p>
            </div>
            #endif
        </div>

        <!-- ── ROUTING DEBUGGER ── -->
        <div class="anim-6 glass-card overflow-hidden bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl">

            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200/50 dark:border-white/5 bg-slate-50/50 dark:bg-white/2">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 tracking-tight">Routing</h3>
                        <p class="section-label mt-0.5">Internal Request State</p>
                    </div>
                </div>
            </div>

            <div class="p-6 space-y-6">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
                    <div>
                        <div class="section-label mb-2">Route Name</div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-violet-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" stroke-width="2" stroke-linecap="round" />
                            </svg>
                            <span class="font-mono text-sm font-bold text-violet-600 dark:text-violet-300 break-all">
                                [[ $current_route_name ?? 'unnamed_route' ]]
                            </span>
                        </div>
                    </div>

                    <div>
                        <div class="section-label mb-2">Controller Action</div>
                        #if(!empty($current_route_action))
                        <div class="font-mono text-xs flex flex-wrap items-center gap-1.5 leading-6">
                            <span class="text-slate-500 dark:text-slate-400 break-all">[[ $current_route_action ]]</span>
                        </div>
                        #else
                        <span class="text-xs italic text-slate-400 leading-6">Closure / No Action</span>
                        #endif
                    </div>
                </div>

                <!-- Middleware -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2 text-indigo-500 dark:text-indigo-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span class="section-label">Middleware</span>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-500 border border-indigo-500/20">
                            [[ count($current_middleware ?? []) ]] Layers
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        #foreach(($current_middleware ?? []) as $mw)
                        <div class="mw-chip">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 dark:bg-indigo-500 shrink-0"></span>
                            [[ $mw ]]
                        </div>
                        #endforeach
                    </div>
                </div>

                <!-- Route Params — IMPROVED: dotted connector line -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2 text-emerald-500 dark:text-emerald-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="section-label">Route Parameters</span>
                        </div>
                        #if (!empty($current_route_params))
                        <span class="text-[10px] font-bold tabular-nums text-emerald-500 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                            [[ count($current_route_params) ]]
                        </span>
                        #endif
                    </div>

                    #if (!empty($current_route_params))
                    <div class="space-y-0.5">
                        #foreach ($current_route_params as $key => $val)
                        <div class="flex items-baseline group py-1.5">
                            <span class="font-mono text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tight shrink-0">[[ $key ]]</span>
                            <span class="param-dot-line group-hover:border-slate-300 dark:group-hover:border-white/10 transition-colors"></span>
                            <span class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400 shrink-0 break-all">[[ $val ]]</span>
                        </div>
                        #endforeach
                    </div>
                    #else
                    <div class="flex items-center gap-2 py-1 opacity-40">
                        <div class="w-1 h-1 rounded-full bg-slate-400"></div>
                        <span class="text-xs font-mono italic text-slate-500">No parameters</span>
                    </div>
                    #endif
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center py-4 text-[10px] font-mono text-slate-300 dark:text-slate-700 tracking-[0.2em] uppercase">
            Doppar Framework
        </div>

    </div><!-- /content -->

    <textarea id="mdContent" class="hidden">[[ $md_content ]]</textarea>

    <script>
        // ── Theme ──
        const ThemeManager = {
            getTheme() {
                const s = localStorage.getItem('theme');
                return s || 'system';
            },
            applyTheme(t) {
                const isDark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', isDark);
                localStorage.setItem('theme', t);
            },
            init() {
                this.applyTheme(this.getTheme());
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                    if (localStorage.getItem('theme') === 'system' || !localStorage.getItem('theme')) {
                        this.applyTheme('system');
                    }
                });
                document.getElementById('themeToggle')?.addEventListener('click', () => {
                    const cur = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                    this.applyTheme(cur === 'dark' ? 'light' : 'dark');
                });
            }
        };
        ThemeManager.init();

        // ── Copy as Markdown ──
        document.getElementById('copyToClipBoard')?.addEventListener('click', async function() {
            const md = document.getElementById('mdContent')?.value;
            if (!md) return;
            try {
                await navigator.clipboard.writeText(md);
                const orig = this.innerHTML;
                this.innerHTML = '<svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
                this.classList.add('bg-green-500/10', '!border-green-400/30');
                setTimeout(() => {
                    this.innerHTML = orig;
                    this.classList.remove('bg-green-500/10', '!border-green-400/30');
                }, 2000);
            } catch {
                this.classList.add('bg-red-500/10');
                setTimeout(() => this.classList.remove('bg-red-500/10'), 1000);
            }
        });

        // ── Request Body Toggle ──
        (function() {
            const toggle = document.getElementById('reqBodyToggle');
            const panel = document.getElementById('reqBodyPanel');
            const arrow = document.getElementById('reqBodyArrow');
            if (!toggle || !panel) return;
            toggle.addEventListener('click', () => {
                const open = panel.style.display === 'block';
                panel.style.display = open ? 'none' : 'block';
                if (arrow) arrow.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
            });
        })();

        // ── Stack Trace Toggle ──
        (function() {
            const container = document.getElementById('traceFrames');
            if (!container) return;

            container.addEventListener('click', function(e) {
                const header = e.target.closest('[data-frame-toggle]');
                if (!header) return;
                const id = header.getAttribute('data-frame-toggle');
                const body = container.querySelector('[data-frame-body="' + id + '"]');
                const arrow = header.querySelector('.arrow-icon');
                if (!body) return;
                const open = body.style.display === 'block';
                body.style.display = open ? 'none' : 'block';
                header.setAttribute('aria-expanded', String(!open));
                if (arrow) arrow.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
            });

            container.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                const h = e.target.closest('[data-frame-toggle]');
                if (h) {
                    e.preventDefault();
                    h.click();
                }
            });

            const btn = document.getElementById('toggleAllFramesBtn');
            const text = document.getElementById('toggleAllText');
            let allOpen = false;
            btn?.addEventListener('click', function() {
                allOpen = !allOpen;
                container.querySelectorAll('[data-frame-body]').forEach(b => {
                    b.style.display = allOpen ? 'block' : 'none';
                });
                container.querySelectorAll('[data-frame-toggle]').forEach(h => {
                    h.setAttribute('aria-expanded', String(allOpen));
                    const a = h.querySelector('.arrow-icon');
                    if (a) a.style.transform = allOpen ? 'rotate(180deg)' : 'rotate(0deg)';
                });
                if (text) text.textContent = allOpen ? 'Collapse All' : 'Expand All';
            });

            // Frame count badge
            const count = container.querySelectorAll('[data-frame-toggle]').length;
            const badge = document.getElementById('frameCountBadge');
            if (badge && count > 0) badge.textContent = count + ' frames';
        })();

        // ── Headers Toggle ──
        (function() {
            const toggle = document.querySelector('[data-headers-toggle]');
            const panel = document.querySelector('[data-headers-panel]');
            if (!toggle || !panel) return;
            toggle.addEventListener('click', () => {
                const open = panel.style.display === 'block';
                panel.style.display = open ? 'none' : 'block';
                toggle.setAttribute('aria-expanded', String(!open));
                const arrow = toggle.querySelector('.arrow-icon');
                if (arrow) arrow.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
            });
            toggle.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggle.click();
                }
            });
        })();
    </script>
</body>

</html>