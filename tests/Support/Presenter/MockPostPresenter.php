<?php

namespace Tests\Support\Presenter;

use Phaseolies\Support\Presenter\Presenter;

class MockPostPresenter extends Presenter
{
    /**
     * Transform the post model to array
     *
     * @return array
     */
    protected function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'status' => $this->status ?? 1,
            'views' => $this->views ?? 0,
            'created_at' => $this->created_at ?? null,

            // Relationships
            'user' => $this->user ?? null,
            'comments' => $this->comments ?? null,
            'tags' => $this->tags ?? null,

            // Conditional fields
            'is_published' => $this->when(
                isset($this->status) && $this->status === 1,
                true,
                false
            ),

            // Computed fields
            'comments_count' => $this->comments_count ?? 0,
            'tags_count' => $this->tags_count ?? 0,

            // Merge when conditions
            ...$this->mergeWhen(
                isset($this->user),
                [
                    'author_name' => $this->user->name ?? null,
                    'author_email' => $this->user->email ?? null,
                ]
            ),
        ];
    }
}
