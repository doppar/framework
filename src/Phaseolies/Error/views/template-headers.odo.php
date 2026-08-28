<button class="kv-toggle" id="headersToggle" aria-expanded="true">
    <div class="panel-head" style="border-bottom:none;">
        <h2>Headers</h2>
        <span class="count-badge">[[ count($headers) ]]</span>
        <svg class="chev" style="margin-left:auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </div>
</button>
<div class="kv-panel" id="headersPanel" style="display:block;border-top:1px solid var(--line)">
    <div class="kv-list">
        #foreach ($headers as $header_name => $header_value)
        <div class="kv-row">
            <span class="kv-key">[[ $header_name ]]</span>
            <span class="kv-dots"></span>
            <span class="kv-val">[[ $header_value ]]</span>
        </div>
        #endforeach
    </div>
</div>
