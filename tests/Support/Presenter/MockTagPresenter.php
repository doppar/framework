<?php

namespace Tests\Support\Presenter;

use Phaseolies\Support\Presenter\Presenter;

class MockTagPresenter extends Presenter
{
    /**
     * Transform the tag model to array
     *
     * @return array
     */
    protected function toArray(): array
    {
        return [
            'id' => $this->presenter->id,
            'name' => $this->presenter->name,

            // Relationships
            'posts' => $this->presenter->posts ?? null,

            // Computed fields
            'posts_count' => $this->presenter->posts_count ?? 0,

            // Using value() method with closure
            'slug' => $this->value(function () {
                return strtolower(str_replace(' ', '-', $this->presenter->name));
            }),
        ];
    }
}