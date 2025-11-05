@if (empty($traces))
    <div class="text-neutral-500 text-sm p-4">No stack trace available</div>
@else
    <div class="space-y-2">
        @foreach ($traces as $index => $trace)
            @php
                $trace['line'] = $trace['line'] ?? 0;
                $function = $trace['function'] ?? '';
                $class = $trace['class'] ?? '';
                $type = $trace['type'] ?? '';
                $signature = $class ? $class . $type . $function . '()' : $function . '()';
            @endphp

            <div class="frame border border-neutral-200 dark:border-neutral-800 rounded-lg overflow-hidden transition-all duration-200 hover:border-neutral-300 dark:hover:border-neutral-700 {{ $trace['is_vendor'] ? 'opacity-60' : '' }}"
                data-frame="{{ $index }}">
                <div class="frame-header flex items-center gap-3 p-3 bg-neutral-50 dark:bg-neutral-900 cursor-pointer"
                    onclick="toggleTraceFrame({{ $index }})">
                    <span
                        class="frame-number flex items-center justify-center w-8 h-8 bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 rounded font-mono text-xs font-semibold shrink-0">{{ $index + 1 }}</span>
                    <div class="flex-1 min-w-0">
                        <div
                            class="font-mono text-sm font-medium truncate {{ $trace['is_vendor'] ? 'text-neutral-500 dark:text-neutral-600' : '' }}">
                            {{ $signature }}</div>
                        <div class="text-xs text-neutral-500 font-mono truncate">
                            <div class="text-xs text-neutral-500 font-mono truncate">
                                {{ ($trace['short_file'] ?? $trace['file']) . ':' . $trace['line'] }}
                            </div>
                        </div>
                    </div>
                    <svg class="frame-arrow w-5 h-5 text-neutral-400 transition-transform duration-200 shrink-0"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
                <div
                    class="frame-content bg-neutral-50/50 dark:bg-neutral-950/50 border-t border-neutral-200 dark:border-neutral-800 hidden frame-content trace-frame-content">
                    <div class="p-3 font-mono text-xs">
                        @foreach ($trace['lines'] as $idx => $lineContent)
                            @php
                                $lineNumber = max(0, $trace['line'] - 4) + $idx + 1;
                                $isHighlight = $lineNumber === $trace['line'];
                            @endphp
                            <div
                                class="flex py-0.5 px-2 {{ $isHighlight ? 'bg-red-500/10 border-l-2 border-l-red-500 text-red-700 dark:text-red-400' : 'text-neutral-600 dark:text-neutral-400' }}">
                                <span
                                    class="inline-block w-10 text-right pr-3 text-neutral-400 select-none shrink-0">{{ $lineNumber }}</span>
                                <span class="flex-1 whitespace-pre">
                                    <pre>
                                        {!! $lineContent !!}
                                    </pre>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<script>
    let allExpanded = false;

    function toggleTraceFrame(index) {
        const frame = document.querySelector(`[data-frame="${index}"]`);
        const content = frame.querySelector('.frame-content');
        const arrow = frame.querySelector('.frame-arrow');

        content.classList.toggle('hidden');
        arrow.style.transform = content.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
    }


    function toggleAllFrames() {
        const frames = document.querySelectorAll('.frame');
        const toggleBtn = document.getElementById('toggleAllText');

        allExpanded = !allExpanded;

        frames.forEach((frame) => {
            const content = frame.querySelector('.frame-content');
            const arrow = frame.querySelector('.frame-arrow');

            if (allExpanded) {
                content.classList.remove('hidden');
                arrow.style.transform = 'rotate(180deg)';
            } else {
                content.classList.add('hidden');
                arrow.style.transform = 'rotate(0deg)';
            }
        });

        toggleBtn.textContent = allExpanded ? 'Collapse All' : 'Expand All';
    }
</script>
