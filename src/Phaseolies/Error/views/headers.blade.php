<div 
    id="single-accordion-container"
    data-open-by-default
    class=" dark:bg-neutral-900 bg-neutral-900/[1%]"
>
    <div
        class="border border-neutral-200 dark:border-neutral-800 rounded-lg overflow-hidden transition-all duration-200 border-dashed"
    >
        {{-- header area --}}
        <div
            class="accordion-header flex items-center gap-3 p-3 bg-neutral-50 dark:bg-neutral-900 cursor-pointer"
            tabindex="0"
            role="button" 
            aria-expanded="false" 
            aria-controls="accordion-content"
        >
            <div class="flex-1 min-w-0">
                <div class="font-mono text-lg font-bold truncate">Headers</div>
                <div class="text-xs text-neutral-500 font-mono truncate">expand to see all headers</div>
            </div>
            <svg class="accordion-arrow w-5 h-5 text-neutral-400 transition-transform duration-200 shrink-0"
                fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        {{-- contaainer area --}}
        <div class="accordion-content bg-white dark:bg-neutral-950/30 border-t border-dashed  border-neutral-200 dark:border-neutral-800 hidden"
            role="region" aria-labelledby="accordion-header">
            <div class="p-3 font-mono">
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
