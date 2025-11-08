<?php

namespace Tests\Support\Presenter;

use Phaseolies\Support\Presenter\Presenter;

class MockCommentPresenter extends Presenter
{
    /**
     * Transform the comment model to array
     *
     * @return array
     */
    protected function toArray(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'approved' => $this->approved ?? 0,
            'created_at' => $this->created_at ?? null,

            // Relationships
            'user' => $this->user ?? null,
            'post' => $this->post ?? null,

            // Conditional fields
            'is_approved' => $this->when(
                isset($this->approved) && $this->approved === 1,
                true,
                false
            ),

            // Unless conditional (opposite of when)
            'needs_review' => $this->unless(
                isset($this->approved) && $this->approved === 1,
                true,
                false
            ),
        ];
    }
}
