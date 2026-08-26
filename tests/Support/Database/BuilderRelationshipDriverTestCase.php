<?php

namespace Tests\Support\Database;

use Phaseolies\Database\Entity\Builder;
use Tests\Support\Model\MockComment;
use Tests\Support\Model\MockPost;
use Tests\Support\Model\MockUser;

abstract class BuilderRelationshipDriverTestCase extends ModelQueryDriverTestCase
{
    protected function tableDefinitions(): array
    {
        return [
            'users' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'email', 'type' => 'string', 'nullable' => true, 'unique' => true],
            ],
            'posts' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'user_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'category_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'title', 'type' => 'string'],
                ['name' => 'content', 'type' => 'text', 'nullable' => true],
                ['name' => 'status', 'type' => 'boolean', 'nullable' => true, 'default' => 1],
                ['name' => 'views', 'type' => 'integer', 'nullable' => true, 'default' => 0],
                ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true],
            ],
            'comments' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'post_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'user_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'body', 'type' => 'text'],
                ['name' => 'approved', 'type' => 'boolean', 'nullable' => true, 'default' => 0],
                ['name' => 'status', 'type' => 'boolean', 'nullable' => true, 'default' => 1],
                ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true],
            ],
            'tags' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'name', 'type' => 'string'],
            ],
            'post_tag' => [
                ['name' => 'post_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'tag_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true],
            ],
            'categories' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'name', 'type' => 'string'],
            ],
        ];
    }

    protected function seedData(): array
    {
        return [
            'users' => [
                ['name' => 'John Doe', 'email' => 'john@example.com'],
                ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ],
            'categories' => [
                ['name' => 'Engineering'],
                ['name' => 'Product'],
            ],
            'posts' => [
                ['user_id' => 1, 'category_id' => 1, 'title' => 'First Post', 'content' => 'Content 1', 'status' => 1, 'views' => 100, 'created_at' => '2024-01-01 11:00:00'],
                ['user_id' => 1, 'category_id' => 1, 'title' => 'Second Post', 'content' => 'Content 2', 'status' => 0, 'views' => 50, 'created_at' => '2024-01-02 11:00:00'],
                ['user_id' => 1, 'category_id' => 2, 'title' => 'Jane Post', 'content' => 'Content 3', 'status' => 1, 'views' => 150, 'created_at' => '2024-01-03 11:00:00'],
            ],
            'comments' => [
                ['post_id' => 1, 'user_id' => 1, 'body' => 'Great post!', 'approved' => 1, 'status' => 1, 'created_at' => '2024-01-01 12:00:00'],
                ['post_id' => 1, 'user_id' => 2, 'body' => 'Nice work', 'approved' => 0, 'status' => 0, 'created_at' => '2024-01-01 13:00:00'],
                ['post_id' => 2, 'user_id' => 1, 'body' => 'Interesting', 'approved' => 1, 'status' => 1, 'created_at' => '2024-01-02 12:00:00'],
                ['post_id' => 3, 'user_id' => 2, 'body' => 'Amazing', 'approved' => 1, 'status' => 1, 'created_at' => '2024-01-03 12:00:00'],
            ],
            'tags' => [
                ['name' => 'PHP'],
                ['name' => 'Doppar'],
                ['name' => 'Testing'],
            ],
            'post_tag' => [
                ['post_id' => 1, 'tag_id' => 1, 'created_at' => '2024-01-01 11:00:00'],
                ['post_id' => 1, 'tag_id' => 2, 'created_at' => '2024-01-01 11:00:00'],
                ['post_id' => 2, 'tag_id' => 1, 'created_at' => '2024-01-02 11:00:00'],
                ['post_id' => 3, 'tag_id' => 3, 'created_at' => '2024-01-03 11:00:00'],
            ],
        ];
    }

    protected function createBuilder(string $table = 'users', string $model = MockUser::class): Builder
    {
        return new Builder($this->pdo, $table, $model, 15);
    }

    protected function createPostBuilder(): Builder
    {
        return $this->createBuilder('posts', MockPost::class);
    }

    protected function createCommentBuilder(): Builder
    {
        return $this->createBuilder('comments', MockComment::class);
    }

    protected function getBuilderConditions(Builder $builder): array
    {
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('conditions');

        return $property->getValue($builder);
    }

    protected function getBuilderEagerLoad(Builder $builder): array
    {
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('eagerLoad');

        return $property->getValue($builder);
    }

    protected function getBuilderLimit(Builder $builder): ?int
    {
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('limit');

        return $property->getValue($builder);
    }

    protected function getBuilderFields(Builder $builder): array
    {
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('fields');

        return $property->getValue($builder);
    }
}
