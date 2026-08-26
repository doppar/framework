#if (empty($traces))
<div class="flex flex-col items-center justify-center py-10 text-slate-300 dark:text-slate-700">
  <p class="text-xs font-mono tracking-widest uppercase">No stack trace available</p>
</div>
#else
#foreach ($traces as $index => $trace)
<div class="frame" data-frame="[[ $index ]]">
  <div class="frame-toggle" data-frame-toggle="[[ $index ]]" tabindex="0" role="button" aria-expanded="false">
    <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/6 text-slate-600 dark:text-slate-400 font-mono text-[11px] font-bold shrink-0">
      [[ $index + 1 ]]
    </span>
    <div class="flex-1 min-w-0">
      <div class="font-mono text-xs font-medium text-slate-800 dark:text-slate-200 truncate">[[ $trace->getCallSignature() ]]</div>
      <div class="text-[11px] text-slate-400 dark:text-slate-600 font-mono truncate mt-0.5">[[ $trace->getShortFile() ]]</div>
    </div>
    <svg class="arrow-icon w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
  </div>
  <div class="frame-body border-t border-black/5 dark:border-white/5 bg-white/40 dark:bg-black/10" data-frame-body="[[ $index ]]">
    <div class="p-4 font-mono text-xs overflow-x-auto">
      [[! $trace->getCodeLinesContent() !]]
    </div>
  </div>
</div>
#endforeach
#endif