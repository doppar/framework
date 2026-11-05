#if (empty($traces))
<div class="empty-state" style="padding:34px 0;">
    <span>No stack trace available</span>
</div>
#else
#foreach ($traces as $index => $trace)
<div class="frame" data-vendor="[[ $trace->isVendor() ? '1' : '0' ]]" data-search="[[ strtolower($trace->getCallSignature() . ' ' . $trace->getShortFile()) ]]">
    <span class="node">[[ $index + 1 ]]</span>
    <button class="frame-toggle" data-toggle="[[ $index ]]" aria-expanded="false">
        <span class="frame-main">
            <span class="frame-sig">[[ $trace->getCallSignature() ]]</span>
            <span class="frame-file">[[ $trace->getShortFile() ]][[ $trace->getLine() > 0 ? ':' . $trace->getLine() : '' ]]</span>
        </span>
        #if ($trace->isVendor())
        <span class="vendor-tag">vendor</span>
        #endif
        <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div class="frame-body" data-body="[[ $index ]]">
        <div class="code">[[! $trace->getCodeLinesContent() !]]</div>
    </div>
</div>
#endforeach
#endif
