<?php

namespace Tests\Unit\Support\Concurrency;

use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\EventLoop\StreamSelectLoop;
use Phaseolies\Support\Concurrency\Task;
use Phaseolies\Support\Concurrency\TaskGroup;
use Phaseolies\Support\Concurrency\Exceptions\DeadlineExceededException;
use Phaseolies\Support\Concurrency\Exceptions\TaskCancelledException;
use Phaseolies\Support\Concurrency\Exceptions\TaskFailedException;

final class TaskGroupTest extends TestCase
{
    public function testItRunsNamedTasksAndPreservesRegistrationOrder(): void
    {
        $order = [];

        $results = TaskGroup::run([
            'slow' => function (Task $task) use (&$order) {
                $order[] = 'slow:start';

                return $task->await(function ($resolve) {
                    Loop::futureTick(fn () => $resolve('slow result'));
                });
            },
            'fast' => function () use (&$order) {
                $order[] = 'fast';

                return 'fast result';
            },
        ]);

        $this->assertSame([
            'slow' => 'slow result',
            'fast' => 'fast result',
        ], $results);
        $this->assertSame(['slow:start', 'fast'], $order);
    }

    public function testNamedFailureCancelsSuspendedSiblings(): void
    {
        $cancelled = false;
        $timer = null;

        try {
            TaskGroup::run([
                'waiting' => function (Task $task) use (&$cancelled, &$timer) {
                    try {
                        return $task->await(function ($resolve) use (&$timer) {
                            $timer = Loop::addTimer(10, fn () => $resolve('late'));

                            return fn () => Loop::cancelTimer($timer);
                        });
                    } catch (TaskCancelledException $cancellation) {
                        $cancelled = true;

                        throw $cancellation;
                    }
                },
                'broken' => fn () => throw new \RuntimeException('boom'),
            ]);

            $this->fail('The task group should have failed.');
        } catch (TaskFailedException $failure) {
            $this->assertSame('broken', $failure->taskName());
            $this->assertSame('boom', $failure->getPrevious()?->getMessage());
            $this->assertTrue($cancelled);
        }
    }

    public function testCancellationCannotBeSwallowedByASibling(): void
    {
        $cleanupRan = false;
        $continued = false;

        try {
            TaskGroup::run([
                'waiting' => function (Task $task) use (&$cleanupRan, &$continued) {
                    try {
                        $task->await(function ($resolve) use (&$cleanupRan) {
                            $timer = Loop::addTimer(10, fn () => $resolve('late'));

                            return function () use ($timer, &$cleanupRan): void {
                                Loop::cancelTimer($timer);
                                $cleanupRan = true;
                            };
                        });
                    } catch (TaskCancelledException) {
                        $continued = true;
                    }

                    return 'ignored';
                },
                'broken' => fn () => throw new \RuntimeException('boom'),
            ]);

            $this->fail('The task group should have failed.');
        } catch (TaskFailedException $failure) {
            $this->assertSame('broken', $failure->taskName());
            $this->assertTrue($cleanupRan);
            $this->assertTrue($continued);
        }
    }

    public function testSharedDeadlineCancelsTheGroup(): void
    {
        $this->expectException(DeadlineExceededException::class);

        TaskGroup::run([
            'waiting' => function (Task $task) {
                return $task->await(function ($resolve) {
                    $timer = Loop::addTimer(10, fn () => $resolve('late'));

                    return fn () => Loop::cancelTimer($timer);
                });
            },
        ], 0.0);
    }

    public function testItRejectsDuplicateAndLateTasks(): void
    {
        $group = new TaskGroup();
        $group->spawn('one', fn () => 1);

        try {
            $group->spawn('one', fn () => 2);
            $this->fail('Duplicate task names should be rejected.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(['one' => 1], $group->await());
        }

        $this->expectException(\LogicException::class);
        $group->spawn('late', fn () => 3);
    }

    public function testItRejectsNestedTaskGroups(): void
    {
        try {
            TaskGroup::run([
                'outer' => fn () => TaskGroup::run([
                    'inner' => fn () => 'result',
                ]),
            ]);

            $this->fail('Nested task groups should be rejected.');
        } catch (TaskFailedException $failure) {
            $this->assertSame('outer', $failure->taskName());
            $this->assertInstanceOf(\LogicException::class, $failure->getPrevious());
        }
    }

    public function testItUsesAnInjectedEventLoop(): void
    {
        $loop = new StreamSelectLoop();

        $results = TaskGroup::run([
            'injected' => function (Task $task) use ($loop) {
                return $task->await(function ($resolve) use ($loop) {
                    $loop->futureTick(fn () => $resolve('result'));
                });
            },
        ], loop: $loop);

        $this->assertSame(['injected' => 'result'], $results);
    }

    public function testItSuspendsInsideAnAlreadyRunningLoopWithoutStoppingIt(): void
    {
        $loop = new StreamSelectLoop();
        $results = null;
        $loopContinued = false;

        $loop->futureTick(function () use ($loop, &$results): void {
            $fiber = new \Fiber(function () use ($loop, &$results): void {
                $results = TaskGroup::run([
                    'embedded' => function (Task $task) use ($loop) {
                        return $task->await(function ($resolve) use ($loop) {
                            $timer = $loop->addTimer(0.001, fn () => $resolve('result'));

                            return fn () => $loop->cancelTimer($timer);
                        });
                    },
                ], loop: $loop);
            });

            $fiber->start();
        });

        $loop->futureTick(function (): void {
            TaskGroup::cancelPending(new \RuntimeException('other request'), 'other-scope');
            TaskGroup::assertNothingPending('other-scope');
        });

        $loop->addTimer(0.002, function () use (&$loopContinued): void {
            $loopContinued = true;
        });

        $loop->run();

        $this->assertSame(['embedded' => 'result'], $results);
        $this->assertTrue($loopContinued);
    }

    protected function tearDown(): void
    {
        TaskGroup::cancelPending();
        TaskGroup::assertNothingPending();
    }
}
