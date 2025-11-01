@php
    if (!file_exists($file) || $line <= 0) {
        $lines = [];
    } else {
        $fileLines = file($file);
        $startLine = max(0, $line - 4);
        $endLine = min(count($fileLines), $line + 3);
        $lines = array_slice($fileLines, $startLine, $endLine - $startLine);
    }
@endphp

@empty($lines)
    <div class="p-3 text-sm text-neutral-500">File preview not available</div>
@else
    <div class="trace-frame-preview">
        @foreach($lines as $index => $lineContent)
            @php
                $lineNumber = max(0, $line - 4) + $index + 1;
                $isHighlight = $lineNumber === $line;
            @endphp
            
            <div class="{{ $isHighlight ? 'preview-line-error' : 'preview-line' }}">
                <span class="preview-line-number">{{ $lineNumber }}</span>
                <span class="preview-line-content">{{ $lineContent }}</span>
            </div>
        @endforeach
    </div>
@endempty