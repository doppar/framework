@empty($traces)
    <div class="text-neutral-500 text-sm p-4">No stack trace available</div>
@else
    <div class="space-y-2">
        @foreach($traces as $index => $trace)
            @php
                $file = $trace['file'] ?? 'unknown';
                $line = $trace['line'] ?? 0
                $function = $trace['function'] ?? '';
                $class = $trace['class'] ?? '';
                $type = $trace['type'] ?? '';
                $signature = $class ? $class . $type . $function . '()' : $function . '()';
                $isVendor = strpos($file, 'doppar/framework') !== false;
            @endphp

            <div class="trace-frame {{ $isVendor ? 'vendor-frame' : '' }}" data-frame="{{ $index }}">
                <div class="trace-frame-header" onclick="toggleTraceFrame({{ $index }})">
                    <span class="trace-frame-number">{{ $index + 1 }}</span>
                    <div class="trace-frame-info">
                        <div class="trace-frame-signature">{{ $signature }}</div>
                        <div class="trace-frame-path">{{ $trace['short_file'] ?? $file }}:{{ $line }}</div>
                    </div>
                    <svg class="trace-frame-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
                <div class="frame-content trace-frame-content hidden">
                    @include('file-preview', ['file' => $file, 'line' => $line])
                </div>
            </div>
        @endforeach
    </div>
@endempty