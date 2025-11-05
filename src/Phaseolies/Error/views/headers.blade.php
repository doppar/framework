<div class="dark:bg-neutral-900 bg-neutral-900/[1%]">
    <div
        class="border border-neutral-200 dark:border-neutral-800 rounded-lg overflow-hidden transition-all duration-200 hover:border-neutral-300 dark:hover:border-neutral-700">
        <div id="accordion-header" class="flex items-center gap-3 p-3 bg-neutral-50 dark:bg-neutral-900 cursor-pointer"
            tabindex="0" role="button" aria-expanded="false" aria-controls="accordion-content">
            <div class="flex-1 min-w-0">
                <div class="font-mono text-sm font-medium truncate">Headers</div>
                <div class="text-xs text-neutral-500 font-mono truncate">expand to see all headers</div>
            </div>
            <svg id="accordion-arrow" class="w-5 h-5 text-neutral-400 transition-transform duration-200 shrink-0"
                fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="accordion-content"
            class="bg-neutral-50/50 dark:bg-neutral-950/50 border-t border-neutral-200 dark:border-neutral-800 hidden"
            role="region" aria-labelledby="accordion-header">
            <div class="p-3 font-mono text-xs">
                @foreach ($headers as $header_name => $header)
                    <div class="header flex gap-4 dark:text-neutral-400 text-neutral-700 items-center">
                        <span class="uppercase">{{ $header_name }}:</span>
                        <div class="flex-1 dark:border-white/10 border-neutral-900/10 border border-dashed h-[1px]">
                        </div>
                        <span class="ml-auto">{{ $header }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const header = document.getElementById('accordion-header');
        const content = document.getElementById('accordion-content');
        const arrow = document.getElementById('accordion-arrow');

        header.addEventListener('click', () => {
            const isHidden = content.classList.contains('hidden');
            content.classList.toggle('hidden');
            arrow.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
            header.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        });

        header.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                header.click();
            }
        });
    });
</script>
