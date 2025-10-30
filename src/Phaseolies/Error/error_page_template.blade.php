<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Error - {{ error_message }}</title>
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
    }

    @custom-variant dark (&:where(.dark, .dark *));
</style>

<body
    class="px-2 md:px-3 lg:px-4 pt-2 md:pt-3 lg:pt-4 bg-white dark:bg-neutral-950 text-neutral-900 dark:text-neutral-50 transition-colors duration-200">
    <div class="top-bar p-2 border dark:border-white/10 border-neutral-900/10 rounded-lg">
        <div class="flex justify-end mb-4">
            <div>
                <div class="text-red-600 text-3xl">
                    {{ exception_class }}
                </div>
                <div class="text-red-600 text-2xl">
                    {{ error_message }}
                </div>
            </div>
            <div class="ml-auto">
                <button id="themeToggle"
                    class="
                        p-2 cursor-pointer rounded-md  hover:bg-neutral-800/6 dark:hover:bg-white/6  transition-colors border  border-transparent dark:hover:border-white/15 hover:border-neutral-900/10
                    "
                    aria-label="Toggle theme">
                    <svg id="sunIcon" class="hidden dark:block w-5 h-5" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>


                    <svg id="moonIcon" class="block dark:hidden size-5" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                </button>
                <button id="copyToClipBoard"
                    class="
                        p-2 cursor-pointer rounded-md  hover:bg-neutral-800/6 dark:hover:bg-white/6  transition-colors border  border-transparent dark:hover:border-white/15 hover:border-neutral-900/10
                    "
                    aria-label="Toggle theme">
                    <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                    </svg>



                </button>
            </div>
        </div>

        {{ error_file }}
        {{ error_line }}
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

    ThemeManager.init()
</script>

</html>
