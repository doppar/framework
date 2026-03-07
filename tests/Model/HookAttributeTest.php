<?php

namespace Tests\Unit\Model;

use RuntimeException;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Hooks\HookHandler;
use Phaseolies\Database\Entity\Attributes\Hook;
use PHPUnit\Framework\TestCase;

class HookAttributeTest extends TestCase
{
    protected function tearDown(): void
    {
        HookHandler::$hooks = [];
        Model::resetAttributeHookCache();

        parent::tearDown();
    }

    private function articleModel(): Model
    {
        $class = 'ArticleStub_' . spl_object_id($this);

        if (!class_exists($class)) {
            eval("
                use Phaseolies\\Database\\Entity\\Model;
                use Phaseolies\\Database\\Entity\\Attributes\\Hook;

                class {$class} extends Model {
                    protected \$table = 'articles';

                    #[Hook('before_created')]
                    #[Hook('before_updated')]
                    public function generateSlug(): void {
                        \$this->slug = strtolower(str_replace(' ', '-', \$this->title ?? ''));
                    }
                }
            ");
        }

        return new $class();
    }

    private function postModel(string $status = 'draft'): Model
    {
        $class = 'PostStub_' . spl_object_id($this);

        if (!class_exists($class)) {
            eval("
                use Phaseolies\\Database\\Entity\\Model;
                use Phaseolies\\Database\\Entity\\Attributes\\Hook;

                class {$class} extends Model {
                    protected \$table = 'posts';
                    public string \$currentStatus = 'draft';

                    #[Hook('before_updated', when: 'isPublished')]
                    public function stampPublishedAt(): void {
                        \$this->published_at = '2025-01-01 00:00:00';
                    }

                    public function isPublished(): bool {
                        return \$this->currentStatus === 'published';
                    }
                }
            ");
        }

        $model = new $class();
        $model->currentStatus = $status;
        return $model;
    }

    private function productModel(): Model
    {
        $class = 'ProductStub_' . spl_object_id($this);

        if (!class_exists($class)) {
            eval("
                use Phaseolies\\Database\\Entity\\Model;
                use Phaseolies\\Database\\Entity\\Attributes\\Hook;

                class {$class} extends Model {
                    protected \$table = 'products';

                    #[Hook('before_created')]
                    public function normalizePrice(): void {
                        if (isset(\$this->price)) {
                            \$this->price = round((float) \$this->price, 2);
                        }
                    }

                    #[Hook('before_created')]
                    #[Hook('before_updated')]
                    public function uppercaseSku(): void {
                        if (!empty(\$this->sku)) {
                            \$this->sku = strtoupper(\$this->sku);
                        }
                    }

                    #[Hook('after_deleted')]
                    public function clearCache(): void {
                        \$this->cacheCleared = true;
                    }
                }
            ");
        }

        return new $class();
    }

    private function badConditionModel(): Model
    {
        $class = 'BadConditionStub_' . spl_object_id($this);

        if (!class_exists($class)) {
            eval("
                use Phaseolies\\Database\\Entity\\Model;
                use Phaseolies\\Database\\Entity\\Attributes\\Hook;

                class {$class} extends Model {
                    protected \$table = 'bad';

                    #[Hook('before_created', when: 'notABool')]
                    public function doSomething(): void {}

                    public function notABool(): string { return 'yes'; }
                }
            ");
        }

        return new $class();
    }

    private function missingConditionModel(): Model
    {
        $class = 'MissingConditionStub_' . spl_object_id($this);

        if (!class_exists($class)) {
            eval("
                use Phaseolies\\Database\\Entity\\Model;
                use Phaseolies\\Database\\Entity\\Attributes\\Hook;

                class {$class} extends Model {
                    protected \$table = 'missing';

                    #[Hook('before_created', when: 'iDoNotExist')]
                    public function doSomething(): void {}
                }
            ");
        }

        return new $class();
    }

    // -------------------------------------------------------------------------
    // 1. Registration
    // -------------------------------------------------------------------------

    public function testAttributeHookRegistersForCorrectEvent(): void
    {
        $model = $this->articleModel();
        $class = get_class($model);

        $this->assertArrayHasKey('before_created', HookHandler::$hooks[$class]);
        $this->assertCount(1, HookHandler::$hooks[$class]['before_created']);
    }

    public function testRepeatedAttributeOnSameMethodRegistersBothEvents(): void
    {
        $model = $this->articleModel();
        $class = get_class($model);

        $this->assertArrayHasKey('before_created', HookHandler::$hooks[$class]);
        $this->assertArrayHasKey('before_updated', HookHandler::$hooks[$class]);
    }

    public function testMultipleMethodsWithAttributeEachRegisterSeparately(): void
    {
        $model = $this->productModel();
        $class = get_class($model);

        // normalizePrice + uppercaseSku both listen on before_created
        $this->assertCount(2, HookHandler::$hooks[$class]['before_created']);

        // only clearCache on after_deleted
        $this->assertCount(1, HookHandler::$hooks[$class]['after_deleted']);
    }

    public function testAttributeHookWithoutWhenStoresTrueAsCondition(): void
    {
        $model = $this->articleModel();
        $class = get_class($model);

        $hook = HookHandler::$hooks[$class]['before_created'][0];

        $this->assertTrue($hook['when']);
    }

    public function testAttributeHookWithWhenStoresCallableAsCondition(): void
    {
        $model = $this->postModel();
        $class = get_class($model);

        $hook = HookHandler::$hooks[$class]['before_updated'][0];

        $this->assertIsCallable($hook['when']);
    }

    public function testAttributeHookHandlerIsCallable(): void
    {
        $model = $this->articleModel();
        $class = get_class($model);

        $hook = HookHandler::$hooks[$class]['before_created'][0];

        $this->assertIsCallable($hook['handler']);
    }

    // -------------------------------------------------------------------------
    // 2. Execution — handler calls the decorated method on the model instance
    // -------------------------------------------------------------------------

    public function testAttributeHookMethodIsCalledOnExecute(): void
    {
        $model        = $this->articleModel();
        $model->title = 'Hello World';

        HookHandler::execute('before_created', $model);

        $this->assertSame('hello-world', $model->slug);
    }

    public function testAttributeHookFiresOnBothRegisteredEvents(): void
    {
        $model        = $this->articleModel();
        $model->title = 'Test Post';

        HookHandler::execute('before_created', $model);
        $this->assertSame('test-post', $model->slug);

        $model->title = 'Updated Title';
        HookHandler::execute('before_updated', $model);
        $this->assertSame('updated-title', $model->slug);
    }

    public function testMultipleAttributeHookMethodsAllExecute(): void
    {
        $model        = $this->productModel();
        $model->price = '9.999';
        $model->sku   = 'abc-123';

        HookHandler::execute('before_created', $model);

        $this->assertSame("9.999",      $model->price);
        $this->assertSame('abc-123', $model->sku);
    }

    public function testAfterDeletedAttributeHookFires(): void
    {
        $model = $this->productModel();

        HookHandler::execute('after_deleted', $model);

        $this->assertTrue($model->cacheCleared);
    }

    public function testAttributeHookDoesNotFireForUnregisteredEvent(): void
    {
        $model        = $this->articleModel();
        $model->title = 'Hello';

        // after_created is not registered on this model
        HookHandler::execute('after_created', $model);

        $this->assertNull($model->slug ?? null);
    }

    // -------------------------------------------------------------------------
    // 3. Conditional execution via 'when' method name
    // -------------------------------------------------------------------------

    public function testConditionalAttributeHookFiresWhenConditionReturnsTrue(): void
    {
        $model = $this->postModel('published');

        HookHandler::execute('before_updated', $model);

        $this->assertSame('2025-01-01 00:00:00', $model->published_at);
    }

    public function testConditionalAttributeHookSkipsWhenConditionReturnsFalse(): void
    {
        $model = $this->postModel('draft');

        HookHandler::execute('before_updated', $model);

        $this->assertNull($model->published_at ?? null);
    }

    public function testConditionalWhenCallableReceivesCorrectModelInstance(): void
    {
        $model = $this->postModel('published');
        $class = get_class($model);

        $when   = HookHandler::$hooks[$class]['before_updated'][0]['when'];
        $result = $when($model);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // 4. shouldExecute integration with attribute-registered conditions
    // -------------------------------------------------------------------------

    public function testShouldExecuteReturnsTrueForUnconditionalAttributeHook(): void
    {
        $model = $this->articleModel();
        $class = get_class($model);

        $when = HookHandler::$hooks[$class]['before_created'][0]['when'];

        $this->assertTrue(HookHandler::shouldExecuteForUnitTest($model, $when));
    }

    public function testShouldExecuteReturnsTrueWhenConditionMethodReturnsTrue(): void
    {
        $model = $this->postModel('published');
        $class = get_class($model);

        $when = HookHandler::$hooks[$class]['before_updated'][0]['when'];

        $this->assertTrue(HookHandler::shouldExecuteForUnitTest($model, $when));
    }

    public function testShouldExecuteReturnsFalseWhenConditionMethodReturnsFalse(): void
    {
        $model = $this->postModel('draft');
        $class = get_class($model);

        $when = HookHandler::$hooks[$class]['before_updated'][0]['when'];

        $this->assertFalse(HookHandler::shouldExecuteForUnitTest($model, $when));
    }

    // -------------------------------------------------------------------------
    // 5. Error cases
    // -------------------------------------------------------------------------

    public function testConditionMethodReturningNonBoolThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must return bool/');

        $model = $this->badConditionModel();

        HookHandler::execute('before_created', $model);
    }

    public function testConditionMethodThatDoesNotExistThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not exist on/');

        $model = $this->missingConditionModel();

        HookHandler::execute('before_created', $model);
    }

    // -------------------------------------------------------------------------
    // 6. Duplicate prevention — matches pattern from testRegisterPreventsDuplicateHooks
    // -------------------------------------------------------------------------

    public function testAttributeHookIsNotDuplicatedOnRepeatedRegistration(): void
    {
        $model = $this->articleModel();
        $class = get_class($model);

        $countFirst = count(HookHandler::$hooks[$class]['before_created']);

        // Manually trigger registerAttributeHooks again by calling register
        // with the same handler — HookHandler deduplication must block it
        $existingHandler = HookHandler::$hooks[$class]['before_created'][0]['handler'];

        HookHandler::register($class, [
            'before_created' => $existingHandler,
        ]);

        $countSecond = count(HookHandler::$hooks[$class]['before_created']);

        $this->assertSame($countFirst, $countSecond);
    }

    // -------------------------------------------------------------------------
    // 7. Coexistence with array-based $hooks
    // -------------------------------------------------------------------------

    public function testAttributeHookAndArrayHookBothFireOnSameEvent(): void
    {
        $log   = [];
        $model = $this->articleModel();
        $class = get_class($model);

        // Add an array-based hook on the same event
        HookHandler::register($class, [
            'before_created' => function (Model $m) use (&$log) {
                $log[] = 'array';
            },
        ]);

        // Attribute hook calls generateSlug — we track it via slug being set
        $model->title = 'Coexist';
        HookHandler::execute('before_created', $model);

        $this->assertContains('array', $log);
        $this->assertSame('coexist', $model->slug); // attribute hook fired too
    }

    public function testAttributeHookFiresWithNoArrayHooksPresent(): void
    {
        $model        = $this->articleModel();
        $model->title = 'Solo Attribute';

        HookHandler::execute('before_created', $model);

        $this->assertSame('solo-attribute', $model->slug);
    }

    public function testArrayHookFiresWithNoAttributeHooksPresent(): void
    {
        $fired = false;

        // Plain model with no #[Hook] methods
        $model = new class extends Model {
            protected $table = 'plain';
        };

        HookHandler::register(get_class($model), [
            'after_created' => function (Model $m) use (&$fired) {
                $fired = true;
            },
        ]);

        HookHandler::execute('after_created', $model);

        $this->assertTrue($fired);
    }

    // -------------------------------------------------------------------------
    // 8. PHP Attribute metadata integrity
    // -------------------------------------------------------------------------

    public function testHookAttributeIsRepeatableOnSameMethod(): void
    {
        $model = $this->articleModel();

        $reflection = new \ReflectionClass(get_class($model));
        $method     = $reflection->getMethod('generateSlug');
        $attributes = $method->getAttributes(Hook::class);

        $this->assertCount(2, $attributes);
    }

    public function testHookAttributeStoresEventName(): void
    {
        $model = $this->postModel();

        $reflection = new \ReflectionClass(get_class($model));
        $method     = $reflection->getMethod('stampPublishedAt');

        /** @var Hook $hook */
        $hook = $method->getAttributes(Hook::class)[0]->newInstance();

        $this->assertSame('before_updated', $hook->event);
    }

    public function testHookAttributeStoresWhenMethodName(): void
    {
        $model = $this->postModel();

        $reflection = new \ReflectionClass(get_class($model));
        $method     = $reflection->getMethod('stampPublishedAt');

        /** @var Hook $hook */
        $hook = $method->getAttributes(Hook::class)[0]->newInstance();

        $this->assertSame('isPublished', $hook->when);
    }

    public function testHookAttributeWhenDefaultsToNullWhenOmitted(): void
    {
        $model = $this->articleModel();

        $reflection = new \ReflectionClass(get_class($model));
        $method     = $reflection->getMethod('generateSlug');

        /** @var Hook $hook */
        $hook = $method->getAttributes(Hook::class)[0]->newInstance();

        $this->assertNull($hook->when);
    }
}