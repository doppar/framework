<?php

namespace Phaseolies\Support\Blade;

trait BladeCondition
{
    /**
     * Usage: @if ($condition).
     *
     * @param mixed $condition
     * @return string
     */
    protected function compileIf($condition): string
    {
        return "<?php if{$condition}: ?>";
    }

    /**
     * Usage: @elseif (condition).
     *
     * @param mixed $condition
     * @return string
     */
    protected function compileElseif($condition): string
    {
        return "<?php elseif{$condition}: ?>";
    }

    /**
     * Usage: @else.
     *
     * @return string
     */
    protected function compileElse(): string
    {
        return "<?php else: ?>";
    }

    /**
     * Usage: @endif.
     *
     * @return string
     */
    protected function compileEndif(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Usage: @unless($condition).
     *
     * @param mixed $condition
     * @return string
     */
    protected function compileUnless($condition): string
    {
        return "<?php if (! $condition): ?>";
    }

    /**
     * Usage: @endunless.
     *
     * @return string
     */
    protected function compileEndunless(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Usage: @isset($variable).
     *
     * @param mixed $variable
     * @return string
     */
    protected function compileIsset($variable): string
    {
        return "<?php if (isset{$variable}): ?>";
    }

    /**
     * Usage: @endisset.
     *
     * @return string
     */
    protected function compileEndisset(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Usage: @switch ($condition).
     *
     * @param mixed $condition
     * @return string
     */
    protected function compileSwitch($condition): string
    {
        $this->firstCaseSwitch = true;
        return "<?php switch{$condition}:";
    }

    /**
     * Usage: @case ($condition).
     *
     * @param mixed $condition
     * @return string
     */
    protected function compileCase($condition): string
    {
        if ($this->firstCaseSwitch) {
            $this->firstCaseSwitch = false;
            return "case {$condition}: ?>";
        }

        return "<?php case {$condition}: ?>";
    }

    /**
     * Usage: @default.
     *
     * @return string
     */
    protected function compileDefault(): string
    {
        return "<?php default: ?>";
    }

    /**
     * Usage: @break or @break($condition).
     *
     * @param mixed $condition
     * @return string
     */
    protected function compileBreak($condition): string
    {
        if ($condition) {
            preg_match('/\(\s*(-?\d+)\s*\)$/', $condition, $matches);
            return $matches
                ? "<?php break " . max(1, $matches[1]) . "; ?>"
                : "<?php if{$condition} break; ?>";
        }

        return "<?php break; ?>";
    }

    /**
     * Usage: @endswitch.
     *
     * @return string
     */
    protected function compileEndswitch(): string
    {
        return "<?php endswitch; ?>";
    }

    /**
     * Usage: @continue or @continue($condition).
     *
     * @param mixed $condition
     * @return string
     */
    protected function compileContinue($condition): string
    {
        if ($condition) {
            preg_match('/\(\s*(-?\d+)\s*\)$/', $condition, $matches);
            return $matches
                ? "<?php continue " . max(1, $matches[1]) . "; ?>"
                : "<?php if{$condition} continue; ?>";
        }

        return "<?php continue; ?>";
    }

    /**
     * Usage: @exit or @exit($condition).
     *
     * @param mixed $condition
     * @return string
     */
    protected function compileExit($condition): string
    {
        if ($condition) {
            preg_match('/\(\s*(-?\d+)\s*\)$/', $condition, $matches);
            return $matches
                ? "<?php exit " . max(1, $matches[1]) . "; ?>"
                : "<?php if{$condition} exit; ?>";
        }
        return "<?php exit; ?>";
    }

    /**
     * Usage: @php($varName = 'value').
     *
     * @param string $value
     * @return string
     */
    protected function compilePhp($value): string
    {
        return $value ? "<?php {$value}; ?>" : "@php{$value}";
    }

    /**
     * Usage: @json($data).
     *
     * @param mixed $data
     * @return string
     */
    protected function compileJson($data): string
    {
        $default = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

        if (isset($data) && "(" == $data[0]) {
            $data = substr($data, 1, -1);
        }

        $parts = explode(",", $data);
        $options = isset($parts[1]) ? trim($parts[1]) : $default;
        $depth = isset($parts[2]) ? trim($parts[2]) : 512;

        // PHP < 5.5.0 doesn't have the $depth parameter
        if (PHP_VERSION_ID >= 50500) {
            return "<?php echo json_encode($parts[0], $options, $depth) ?>";
        }

        return "<?php echo json_encode($parts[0], $options) ?>";
    }

    /**
     * Usage: @unset($var).
     *
     * @param mixed $variable
     * @return string
     */
    protected function compileUnset($variable): string
    {
        return "<?php unset{$variable}; ?>";
    }

    /**
     * Usage: @for ($condition).
     *
     * @param mixed $condition
     * @return string
     */
    protected function compileFor($condition): string
    {
        return "<?php for{$condition}: ?>";
    }

    /**
     * Usage: @endfor.
     *
     * @return string
     */
    protected function compileEndfor(): string
    {
        return "<?php endfor; ?>";
    }

    /**
     * Usage: @foreach ($expression).
     *
     * @param mixed $expression
     * @return string
     */
    protected function compileForeach($expression): string
    {
        preg_match('/\( *(.*) +as *(.*)\)$/is', $expression, $matches);

        $iteratee = trim($matches[1]);
        $iteration = trim($matches[2]);
        $initLoop = "\$__currloopdata = {$iteratee}; \$this->addLoop(\$__currloopdata);";
        $iterateLoop =
            '$this->incrementLoopIndices(); $loop = $this->getFirstLoop();';

        return "<?php {$initLoop} foreach(\$__currloopdata as {$iteration}): {$iterateLoop} ?>";
    }

    /**
     * Usage: @endforeach.
     *
     * @return string
     */
    protected function compileEndforeach(): string
    {
        return "<?php endforeach; ?>";
    }

    /**
     * Usage: @forelse ($condition).
     *
     * @param mixed $expression
     * @return string
     */
    protected function compileForelse($expression): string
    {
        preg_match('/\( *(.*) +as *(.*)\)$/is', $expression, $matches);

        $iteratee = trim($matches[1]);
        $iteration = trim($matches[2]);
        $initLoop = "\$__currloopdata = {$iteratee}; \$this->addLoop(\$__currloopdata);";
        $iterateLoop =
            '$this->incrementLoopIndices(); $loop = $this->getFirstLoop();';

        ++$this->emptyCounter;

        return "<?php {$initLoop} \$__empty_{$this->emptyCounter} = true;" .
            " foreach(\$__currloopdata as {$iteration}): " .
            "\$__empty_{$this->emptyCounter} = false; {$iterateLoop} ?>";
    }

    /**
     * Usage: @empty.
     *
     * @return string
     */
    protected function compileEmpty(): string
    {
        $string = "<?php endforeach; if (\$__empty_{$this->emptyCounter}): ?>";
        --$this->emptyCounter;

        return $string;
    }

    /**
     * Usage: @endforelse.
     *
     * @return string
     */
    protected function compileEndforelse(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Usage: @while ($condition).
     *
     * @param mixed $condition
     * @return string
     */
    protected function compileWhile($condition): string
    {
        return "<?php while{$condition}: ?>";
    }

    /**
     * Usage: @endwhile.
     *
     * @return string
     */
    protected function compileEndwhile(): string
    {
        return "<?php endwhile; ?>";
    }

    /**
     * Usage: @extends($parent).
     *
     * @param string $parent
     * @return string
     */
    protected function compileExtends($parent): string
    {
        if (isset($parent[0]) && "(" === $parent[0]) {
            $parent = substr($parent, 1, -1);
        }

        return "<?php \$this->addParent({$parent}) ?>";
    }

    /**
     * Usage: @include($view).
     *
     * @param string $view
     * @return string
     */
    protected function compileInclude($expression): string
    {
        if (isset($expression[0]) && "(" === $expression[0]) {
            $expression = substr($expression, 1, -1);
        }

        // Split into view and data
        $parts = explode(',', $expression, 2);
        $view = trim($parts[0]);
        $data = isset($parts[1]) ? trim($parts[1]) : '[]';

        return "<?php \$__data = {$data}; extract(\$__data); include \$this->prepare({$view}); ?>";
    }

    /**
     * Usage: @yield($string).
     *
     * @param string $string
     * @return string
     */
    protected function compileYield($string): string
    {
        return "<?php echo \$this->block{$string} ?>";
    }

    /**
     * Usage: @section($name).
     *
     * @param string $name
     * @return string
     */
    protected function compileSection($name): string
    {
        return "<?php \$this->beginBlock{$name} ?>";
    }

    /**
     * Usage: @endsection.
     *
     * @return string
     */
    protected function compileEndsection(): string
    {
        return '<?php $this->endBlock() ?>';
    }

    /**
     * Usage: @show.
     *
     * @return string
     */
    protected function compileShow(): string
    {
        return '<?php echo $this->block($this->endBlock()) ?>';
    }

    /**
     * Usage: @append.
     *
     * @return string
     */
    protected function compileAppend(): string
    {
        return '<?php $this->endBlock() ?>';
    }

    /**
     * Usage: @stop.
     *
     * @return string
     */
    protected function compileStop(): string
    {
        return '<?php $this->endBlock() ?>';
    }

    /**
     * Usage: @overwrite.
     *
     * @return string
     */
    protected function compileOverwrite(): string
    {
        return '<?php $this->endBlock(true) ?>';
    }

    /**
     * Usage: @method('put').
     *
     * @param string $method
     * @return string
     */
    protected function compileMethod($method): string
    {
        return "<input type=\"hidden\" name=\"_method\" value=\"<?php echo strtoupper{$method} ?>\">\n";
    }

    /**
     * Usage: @csrf
     *
     * Generate random string to protect spumy form submit
     *
     * @return string
     */
    protected function compileCsrf()
    {
        return '<?php echo \'<input type="hidden" name="_token" value="\' . 
        htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") . \'">\'; ?>' . "\n";
    }

    /**
     * Usage: @can('ability-name'[, $args])
     *
     * @param string $expression
     * @return string
     */
    protected function compileScope($expression): string
    {
        return "<?php if (\\Doppar\\Authorizer\\Support\\Facades\\Guard::allows{$expression}): ?>";
    }

    /**
     * Usage: @elsescope('ability-name'[, $args])
     *
     * @param string $expression
     * @return string
     */
    protected function compileElsescope($expression): string
    {
        return "<?php elseif (\\Doppar\\Authorizer\\Support\\Facades\\Guard::allows{$expression}): ?>";
    }

    /**
     * Usage: @endscope
     *
     * @return string
     */
    protected function compileEndscope(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Usage: @scopenot('ability-name'[, $args])
     *
     * @param string $expression
     * @return string
     */
    protected function compileScopenot($expression): string
    {
        return "<?php if (!\\Doppar\\Authorizer\\Support\\Facades\\Guard::allows{$expression}): ?>";
    }

    /**
     * Usage: @elsescopenot('ability-name'[, $args])
     *
     * @param string $expression
     * @return string
     */
    protected function compileElsescopenot($expression): string
    {
        return "<?php elseif (!\\Doppar\\Authorizer\\Support\\Facades\\Guard::allows{$expression}): ?>";
    }

    /**
     * Usage: @endscopenot
     *
     * @return string
     */
    protected function compileEndscopenot(): string
    {
        return "<?php endif; ?>";
    }
}
