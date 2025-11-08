<?php

namespace Tests\Support\Presenter;

use Phaseolies\Support\Presenter\Presenter;

class MockUserPresenter extends Presenter
{
    /**
     * Transform the user model to array
     *
     * @return array
     */
    protected function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'age' => $this->age ?? null,
            'status' => $this->status ?? 'active',
            'created_at' => $this->created_at ?? null,

            // Conditional fields
            'is_active' => $this->when(
                isset($this->status) && $this->status === 'active',
                true,
                false
            ),

            // Nested relationships
            'posts' => $this->posts ?? null,
            'comments' => $this->comments ?? null,

            // Computed fields
            'posts_count' => $this->posts_count ?? 0,
            'comments_count' => $this->comments_count ?? 0,
        ];
    }
}
