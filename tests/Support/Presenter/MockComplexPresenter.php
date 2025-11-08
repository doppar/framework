<?php

namespace Tests\Support\Presenter;

use Phaseolies\Support\Presenter\Presenter;

class MockComplexPresenter extends Presenter
{
    /**
     * Transform with complex logic
     *
     * @return array
     */
    protected function toArray(): array
    {
        return [
            'id' => $this->presenter->id,

            // When with closure
            'computed_value' => $this->when(
                true,
                fn() => 'computed_' . $this->presenter->id
            ),

            // When with default value
            'default_value' => $this->when(
                false,
                'true_value',
                'default_value'
            ),

            // Unless with closure
            'unless_value' => $this->unless(
                false,
                fn() => 'unless_computed'
            ),

            // MergeWhen with array
            ...$this->mergeWhen(true, [
                'merged_key' => 'merged_value',
                'another_key' => 'another_value'
            ]),

            // MergeWhen with false condition
            ...$this->mergeWhen(false, [
                'should_not_appear' => 'value'
            ]),

            // Value with closure
            'closure_value' => $this->value(fn() => 'closure_result'),

            // Value with direct value
            'direct_value' => $this->value('direct'),
        ];
    }
}
