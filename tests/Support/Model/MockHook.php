<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

/**
 * Class MockHook
 *
 * This mock model is used to test Doppar's model hook system.
 * It defines multiple lifecycle hooks — covering booting, creation,
 * update, and deletion — and sets static flags when those hooks are triggered.
 *
 * Each flag can be asserted in unit tests to confirm that Doppar’s
 * internal hook dispatcher behaves as expected.
 */
class MockHook extends Model
{
    protected $table = 'hooks';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['name'];

    // Static flags for verifying that specific hooks were triggered.
    // These flags are set to true by their corresponding callback methods.
    public static bool $wasCalledBeforeBooting = false;
    public static bool $wasCalledAfterCreated = false;
    public static bool $wasCalledAfterUpdated = false;
    public static bool $wasCalledAfterDeleted = false;

    /**
     * Define the model’s lifecycle hooks.
     *
     * Each hook can have:
     *  - 'handler': the callable or class method to execute
     *  - 'when': a boolean or callable condition controlling execution
     *
     * Available hook stages include:
     *  - booting
     *  - booted
     *  - after_created
     *  - after_updated
     *  - after_deleted
     */
    protected $hooks = [
        // Fires when the model is booting.
        'booting' => [
            'handler' => [self::class, 'callbackBeforeBooting'],
            'when' => [self::class, 'shouldTrigger']
        ],

        // Fires immediately after a record is created.
        'after_created' => [
            'handler' => [self::class, 'shouldBeCalledAfterAnItemCreated'],
            'when' => true
        ],

        // Fires immediately after a record is updated.
        'after_updated' => [self::class, 'shouldBeCalledAfterAnItemUpdated'],

        // Fires immediately after a record is deleted.
        // 'when' => false ensures this hook will not triggerd after deleted an item.
        'after_deleted' => [
            'handler' => [self::class, 'shouldBeCalledAfterAnItemDeleted'],
            'when' => false
        ],
    ];

    /**
     * Conditional callback used in the 'booting' hook.
     *
     * @return bool
     */
    public static function shouldTrigger(): bool
    {
        return true;
    }

    /**
     * Called when the model is booting.
     *
     * @param Model $model
     * @return void
     */
    public static function callbackBeforeBooting(Model $model): void
    {
        self::$wasCalledBeforeBooting = true;
    }

    /**
     * Called after the model has been successfully created.
     *
     * @param Model $model
     * @return void
     */
    public static function shouldBeCalledAfterAnItemCreated(Model $model): void
    {
        self::$wasCalledAfterCreated = true;
    }

    /**
     * Called after the model has been updated.
     *
     * @param Model $model
     * @return void
     */
    public static function shouldBeCalledAfterAnItemUpdated(Model $model): void
    {
        self::$wasCalledAfterUpdated = true;
    }

    /**
     * Called after the model has been deleted.
     *
     * @param Model $model
     * @return void
     */
    public static function shouldBeCalledAfterAnItemDeleted(Model $model): void
    {
        self::$wasCalledAfterDeleted = true;
    }
}
