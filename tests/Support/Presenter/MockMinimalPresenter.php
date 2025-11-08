<?php

namespace Tests\Support\Presenter;

use Phaseolies\Support\Presenter\Presenter;

class MockMinimalPresenter extends Presenter
{
    /**
     * Transform to array with minimal fields
     *
     * @return array
     */
    protected function toArray(): array
    {
        return [
            'id' => $this->presenter->id ?? null,
            'name' => $this->presenter->name ?? null,
        ];
    }
}
