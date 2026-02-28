 <div class="headers-toggle border-b border-black/5 dark:border-white/5 bg-black/2 dark:bg-white/2" data-headers-toggle tabindex="0" role="button" aria-expanded="false">
     <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-500/10 border border-sky-100 dark:border-sky-500/20 flex items-center justify-center shrink-0">
         <svg class="w-3.5 h-3.5 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
         </svg>
     </div>
     <div class="flex-1">
         <div class="text-sm font-semibold">Headers</div>
         <div class="text-[11px] text-slate-400 dark:text-slate-600 mt-0.5">HTTP Request Headers</div>
     </div>
     <svg class="arrow-icon w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
     </svg>
 </div>
 <div class="headers-panel bg-white/40 dark:bg-black/10" data-headers-panel>
     <div class="px-5 py-4 font-mono text-xs space-y-1">
         #foreach ($headers as $header_name => $header_value)
         <div class="flex items-center gap-3 py-1.5 ">
             <span class="uppercase text-slate-500 shrink-0 w-44 truncate">[[ $header_name ]]</span>
             <div class="flex-1 border-t border-dashed border-black/10 dark:border-white/10 h-px"></div>
             <span class="text-slate-700 dark:text-slate-300 text-right truncate max-w-xs">[[ $header_value ]]</span>
         </div>
         #endforeach
     </div>
 </div>