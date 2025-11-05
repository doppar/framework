<div class="frames-container">
  @if (empty($traces))
    <div class="text-neutral-500 text-sm p-4">No stack trace available</div>
  @else
    <div class="space-y-2">
      @foreach ($traces as $index => $trace)
      <div
        class="frame border border-neutral-200 dark:border-neutral-800 rounded-lg overflow-hidden transition-all duration-200 hover:border-neutral-300 dark:hover:border-neutral-700"
        data-frame="{{ $index }}"
      >
        <div class="frame-header flex items-center gap-3 p-3 bg-neutral-50 dark:bg-neutral-900 cursor-pointer">
          <span
            class="frame-number flex items-center justify-center w-8 h-8 bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 rounded font-mono text-xs font-semibold shrink-0">{{ $index + 1 }}</span>
          <div class="flex-1 min-w-0">
            <div class="font-mono text-sm font-medium truncate">{{ $trace->getCallSignature() }}</div>
            <div class="text-xs text-neutral-500 font-mono truncate">
              <div class="text-xs text-neutral-500 font-mono truncate">{{ $trace->getShortFile() }}</div>
            </div>
          </div>
          <svg class="frame-arrow w-5 h-5 text-neutral-400 transition-transform duration-200 shrink-0" fill="none"
            stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
          </svg>
        </div>
        <div class="frame-content bg-neutral-50/50 dark:bg-neutral-950/50 border-t border-neutral-200 dark:border-neutral-800 hidden"
          role="region">
          <div class="p-3 font-mono text-xs">
            {!! $trace->getCodeLinesContent() !!}
          </div>
        </div>
      </div>
      @endforeach
    </div>
  @endif
</div>
