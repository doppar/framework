<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Error - {{ $error_message }}</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <script>
        const loadDarkMode = () => {
            const theme = localStorage.getItem('theme') ?? 'system'

            if (
                theme === 'dark' ||
                (theme === 'system' &&
                    window.matchMedia('(prefers-color-scheme: dark)')
                    .matches)
            ) {
                document.documentElement.classList.add('dark')
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
        /* Muted gray for tags */
        --color-hl-variable: #fe640b;
        /* Orange for variables */
        --color-hl-string: #7287fd;
        /* Soft blue for strings */
        --color-hl-definition: #8839ef;
        /* Purple for classes/functions */
        --color-hl-modifier: #df8e1d;
        /* Yellow for modifiers */
        --color-hl-keyword: #d20f39;
        /* Red for keywords */
        --color-hl-literal: #40a02b;
        /* Green for literals */
        --color-hl-comment: #9ca0b0;
        /* Light gray for comments */
        --color-hl-number: #fe640b;
        /* Orange for numbers */
        --color-hl-default: #4c4f69;
        /* Dark gray default */
    }

    @layer theme {
        .dark {
            /* Highlighting Colors - Dark Mode */
            --color-hl-tag: #565f89;
            /* Muted blue-gray */
            --color-hl-variable: #ff9e64;
            /* Warm orange */
            --color-hl-string: #9ece6a;
            /* Fresh green */
            --color-hl-definition: #7aa2f7;
            /* Sky blue */
            --color-hl-modifier: #bb9af7;
            /* Soft purple */
            --color-hl-keyword: #f7768e;
            /* Coral pink */
            --color-hl-literal: #9ece6a;
            /* Fresh green */
            --color-hl-comment: #565f89;
            /* Muted blue-gray */
            --color-hl-number: #ff9e64;
            /* Warm orange */
            --color-hl-default: #c0caf5;
            /* Light blue-white */
        }
    }


    @custom-variant dark (&:where(.dark, .dark *));

    @layer components {
        .badge {
            @apply px-2 py-1 rounded font-medium transition-colors;
        }

        .badge[data-request-type="GET"] {
            @apply bg-green-500/10 text-green-500;
        }

        .badge[data-request-type="POST"] {
            @apply bg-blue-500/10 text-blue-500;
        }

        .badge[data-request-type="PUT"] {
            @apply bg-yellow-500/10 text-yellow-500;
        }

        .badge[data-request-type="DELETE"] {
            @apply bg-red-500/10 text-red-500;
        }

        /* Code Line Components */
        .code-line {
            @apply inline-flex w-full hover:bg-neutral-100 dark:hover:bg-white/5 py-0.5;
        }

        .code-line-error {
            @apply inline-flex w-full bg-red-500/10 border-l-2 border-l-red-500 py-0.5;
        }

        .code-line-number {
            @apply w-12 text-right pr-3 text-neutral-500 select-none shrink-0;
        }

        .code-line-content {
            @apply flex-1;
        }
    }
</style>

<body
    class="px-2 antialiased tracking-wide md:px-3 lg:px-12 py-2 md:py-3 lg:py-4 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-50 transition-colors duration-200">
    {{-- Top Bar --}}
    <div class="top-bar rounded-lg">
        <div
            class="flex border-[1.2px] dark:bg-white/[1%] bg-neutral-900/[1%] dark:border-white/4 border-neutral-900/4 p-4 rounded-lg">
            <div>
                <div class="dark:text-white text-neutral-950 text-3xl font-bold">
                    {{ $exception_class }}
                </div>
                <div class="dark:text-neutral-300 text-neutral-700 text-2xl">
                    {{ $error_message }}
                </div>
            </div>
            <div class="ml-auto flex flex-col">
                <div class="ml-auto">
                    <button id="themeToggle" class="p-2 cursor-pointer rounded-md" aria-label="Toggle theme">
                        <svg id="sunIcon" class="hidden dark:block w-5 h-5" xmlns="http://www.w3.org/2000/svg"
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
                    <button id="copyToClipBoard" class="p-2 cursor-pointer rounded-md" title="copy as markdown">
                        <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                        </svg>
                    </button>
                </div>
                {{-- Versions --}}
                <div
                    class="rounded-md flex border-[1.2px] dark:bg-white/[1%] bg-neutral-900/[1%] text-sm py-1 border-neutral-900/4 px-2 mt-4 dark:border-white/4">
                    <div class="pl-1 pr-2 border-r border-neutral-900/4 dark:border-white/4">
                        <span class="text-neutral-800 dark:text-neutral-500 pr-2">DOPPAR</span> {{ $doppar_version }}
                    </div>
                    <div class="px-2">
                        <span class="text-neutral-800 dark:text-neutral-500 pr-2">PHP</span> {{ $php_version }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Request Details --}}
    <div
        class="flex gap-2 items-center border-[1.2px] dark:bg-white/[1%] bg-neutral-900/[1%] my-5 dark:border-white/4 border-neutral-900/4 p-2 rounded-lg">
        <span data-request-type="{{ $request_method }}" class="badge p-1 rounded">
            {{ $request_method }}
        </span>
        <span class="dark:text-neutral-400">{{ $request_url }}</span>
        <span class="ml-auto">{{ $timestamp }}</span>
    </div>

    {{-- Main Content --}}
    <div class="flex flex-col gap-4">
        <main
            class="rounded-lg border-[1.2px] w-full dark:bg-white/[1%] bg-neutral-900/[1%] border-neutral-900/5 dark:border-white/5 p-2">
            {{-- File Header --}}
            <div class="flex items-center gap-2 bg-neutral-100 dark:bg-white/5 rounded-md px-2 py-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m6.75 7.5 3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                {{ $error_file }}
            </div>

            {{-- Code Content --}}
            <div class="pt-3 w-full">

                <pre>
                    {!! $contents !!}
                </pre>

            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between mb-3 px-2">
                    <h3 class="text-base font-semibold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        Stack Trace
                    </h3>
                    <button id="toggleAllFramesBtn"
                        class="text-sm px-3 py-1 rounded-md bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors duration-200">
                        <span id="toggleAllText">Expand All</span>
                    </button>
                </div>
                <div id="traceFrames">
                    @include('trace-frames', ['traces' => $traces])
                </div>
            </div>

        </main>
        <div id="headers" class="my-4">
            @include('headers', ['headers' => $headers])
        </div>
        
    </div>
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

                toggleAllBtn.textContent = allExpanded ? 'Collapse All' : 'Expand All';
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupAccordion('#single-accordion-container');

        setupAccordion('.frames-container', {
            headerSelector: '.frame-header',
            contentSelector: '.frame-content',
            arrowSelector: '.frame-arrow',
            toggleAllBtnSelector: '#toggleAllFramesBtn',
        });
    });
</script>


</html>
