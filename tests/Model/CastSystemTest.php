<?php

namespace Tests\Unit\Model;

use Carbon\Carbon;
use PDO;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Casts\Attributes\ToArray;
use Phaseolies\Database\Entity\Casts\Attributes\ToBoolean;
use Phaseolies\Database\Entity\Casts\Attributes\ToCollection;
use Phaseolies\Database\Entity\Casts\Attributes\ToDate;
use Phaseolies\Database\Entity\Casts\Attributes\ToDateTime;
use Phaseolies\Database\Entity\Casts\Attributes\ToFloat;
use Phaseolies\Database\Entity\Casts\Attributes\ToInteger;
use Phaseolies\Database\Entity\Casts\Attributes\ToJson;
use Phaseolies\Database\Entity\Casts\Attributes\ToObject;
use Phaseolies\Database\Entity\Casts\Attributes\ToString;
use Phaseolies\Database\Entity\Casts\Attributes\ToTimestamp;
use Phaseolies\Database\Entity\Casts\Attributes\Transform;
use Phaseolies\Database\Entity\Casts\CastManager;
use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;
use Phaseolies\Database\Entity\Casts\Type;
use Phaseolies\Database\Entity\Model;
use Phaseolies\DI\Container;
use Phaseolies\Http\Request;
use Phaseolies\Support\Collection;
use Phaseolies\Support\UrlGenerator;
use PHPUnit\Framework\TestCase;
use Tests\Support\MockContainer;

enum CastTestStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
}

enum CastTestPriority: int
{
    case Low    = 1;
    case Medium = 2;
    case High   = 3;
}

enum CastTestColor
{
    case Red;
    case Green;
    case Blue;
}

class UpperCaseCast implements CastableInterface
{
    public function get(mixed $value): mixed
    {
        return is_string($value) ? strtoupper($value) : $value;
    }

    public function set(mixed $value): mixed
    {
        return is_string($value) ? strtolower($value) : $value;
    }
}

class MockPrimitiveCastModel extends Model
{
    protected $table      = 'cast_records';
    protected $connection = 'default';
    protected $timeStamps = false;

    #[ToString]
    protected string $label;

    #[ToInteger]
    protected string $score;

    #[ToFloat]
    protected string $weight;

    #[ToBoolean]
    protected string $is_active;
}

class MockDateCastModel extends Model
{
    protected $table      = 'cast_records';
    protected $connection = 'default';
    protected $timeStamps = false;

    #[ToDateTime]
    protected string $published_at;

    #[ToDate]
    protected string $birthday;

    #[ToTimestamp]
    protected string $created_ts;
}

class MockStructuralCastModel extends Model
{
    protected $table      = 'cast_records';
    protected $connection = 'default';
    protected $timeStamps = false;

    #[ToArray]
    protected string $options;

    #[ToJson]
    protected string $tags;

    #[ToObject]
    protected string $config;

    #[ToCollection]
    protected string $items;
}

class MockDecimalCastModel extends Model
{
    protected $table      = 'cast_records';
    protected $connection = 'default';
    protected $timeStamps = false;

    #[Transform('decimal:2')]
    protected string $price;

    #[Transform('decimal:4')]
    protected string $tax_rate;
}

class MockEnumCastModel extends Model
{
    protected $table      = 'cast_records';
    protected $connection = 'default';
    protected $timeStamps = false;

    #[Transform(CastTestStatus::class)]
    protected string $status;

    #[Transform(CastTestPriority::class)]
    protected string $priority;

    #[Transform(CastTestColor::class)]
    protected string $color;
}

class MockCustomCastModel extends Model
{
    protected $table      = 'cast_records';
    protected $connection = 'default';
    protected $timeStamps = false;

    #[Transform(UpperCaseCast::class)]
    protected string $name;
}

class MockCustomDateFormatModel extends Model
{
    protected $table      = 'cast_records';
    protected $connection = 'default';
    protected $timeStamps = false;

    #[Transform('datetime:d/m/Y')]
    protected string $invoiced_at;
}

class MockTransformEnumModel extends Model
{
    protected $table      = 'cast_records';
    protected $connection = 'default';
    protected $timeStamps = false;

    #[Transform(Type::String)]
    protected string $label;

    #[Transform(Type::Integer)]
    protected string $score;
}

class MockNoCastsModel extends Model
{
    protected $table      = 'cast_records';
    protected $connection = 'default';
    protected $timeStamps = false;
}

class CastSystemTest extends TestCase
{
    private $pdo;

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());
        $container = new Container();
        $container->bind('request', fn() => new Request());
        $container->bind('url', fn() => UrlGenerator::class);
        $container->bind('db', fn() => new Database('default'));

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTestTable();
        $this->setupDatabaseConnections();

        CastManager::flush();
        MockPrimitiveCastModel::resetCastCache();
        MockDateCastModel::resetCastCache();
        MockStructuralCastModel::resetCastCache();
        MockDecimalCastModel::resetCastCache();
        MockEnumCastModel::resetCastCache();
        MockCustomCastModel::resetCastCache();
        MockCustomDateFormatModel::resetCastCache();
        MockTransformEnumModel::resetCastCache();
        MockNoCastsModel::resetCastCache();
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        $this->tearDownDatabaseConnections();
        CastManager::flush();
    }

    private function createTestTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE cast_records (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                label        TEXT,
                score        TEXT,
                weight       TEXT,
                is_active    TEXT,
                published_at TEXT,
                birthday     TEXT,
                created_ts   TEXT,
                options      TEXT,
                tags         TEXT,
                config       TEXT,
                items        TEXT,
                price        TEXT,
                tax_rate     TEXT,
                status       TEXT,
                priority     TEXT,
                color        TEXT,
                name         TEXT,
                invoiced_at  TEXT
            )
        ");
    }

    private function setupDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', [
            'default' => $this->pdo,
            'sqlite'  => $this->pdo,
        ]);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function tearDownDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function setStaticProperty(string $class, string $property, mixed $value): void
    {
        $ref = new \ReflectionClass($class);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue(null, $value);
        $prop->setAccessible(false);
    }

    private function insertRecord(array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO cast_records ({$cols}) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function testHasCastReturnsTrueForDeclaredProperties(): void
    {
        $model = new MockPrimitiveCastModel();

        $this->assertTrue($model->hasCast('label'));
        $this->assertTrue($model->hasCast('score'));
        $this->assertTrue($model->hasCast('weight'));
        $this->assertTrue($model->hasCast('is_active'));
    }

    public function testHasCastReturnsFalseForUndeclaredProperties(): void
    {
        $model = new MockPrimitiveCastModel();

        $this->assertFalse($model->hasCast('nonexistent'));
        $this->assertFalse($model->hasCast('id'));
    }

    public function testGetCastTypeReturnsCorrectType(): void
    {
        $model = new MockPrimitiveCastModel();

        $this->assertSame('string',  $model->getCastType('label'));
        $this->assertSame('integer', $model->getCastType('score'));
        $this->assertSame('float',   $model->getCastType('weight'));
        $this->assertSame('boolean', $model->getCastType('is_active'));
    }

    public function testGetCastTypeReturnsNullForUnknownKey(): void
    {
        $model = new MockPrimitiveCastModel();

        $this->assertNull($model->getCastType('nonexistent'));
    }

    public function testGetCastsReturnsAllDefinitions(): void
    {
        $model = new MockPrimitiveCastModel();
        $casts = $model->getCasts();

        $this->assertIsArray($casts);
        $this->assertArrayHasKey('label',     $casts);
        $this->assertArrayHasKey('score',     $casts);
        $this->assertArrayHasKey('weight',    $casts);
        $this->assertArrayHasKey('is_active', $casts);
        $this->assertCount(4, $casts);
    }

    public function testModelWithNoCastsReturnsEmptyArray(): void
    {
        $model = new MockNoCastsModel();

        $this->assertSame([], $model->getCasts());
        $this->assertFalse($model->hasCast('label'));
    }

    public function testCacheIsNotPopulatedUntilFirstAccess(): void
    {
        MockPrimitiveCastModel::resetCastCache();

        $ref   = new \ReflectionClass(Model::class);
        $cache = $ref->getProperty('castAttributeCache');
        $cache->setAccessible(true);

        $before = $cache->getValue(null);
        $this->assertArrayNotHasKey(MockPrimitiveCastModel::class, $before);

        $model = new MockPrimitiveCastModel();
        $model->hasCast('label'); // triggers lazy load

        $after = $cache->getValue(null);
        $this->assertArrayHasKey(MockPrimitiveCastModel::class, $after);

        $cache->setAccessible(false);
    }

    public function testCacheIsSharedAcrossInstancesOfSameClass(): void
    {
        $a = new MockPrimitiveCastModel();
        $b = new MockPrimitiveCastModel();

        $a->hasCast('label'); // prime cache via instance a

        $ref   = new \ReflectionClass(Model::class);
        $cache = $ref->getProperty('castAttributeCache');
        $cache->setAccessible(true);
        $data = $cache->getValue(null);
        $cache->setAccessible(false);

        // Both instances share the same static cache entry
        $this->assertArrayHasKey(MockPrimitiveCastModel::class, $data);
        $this->assertTrue($b->hasCast('label'));
    }

    public function testResetCastCacheFlushesSpecificClass(): void
    {
        $model = new MockPrimitiveCastModel();
        $model->hasCast('label');

        MockPrimitiveCastModel::resetCastCache(MockPrimitiveCastModel::class);

        $ref   = new \ReflectionClass(Model::class);
        $cache = $ref->getProperty('castAttributeCache');
        $cache->setAccessible(true);
        $data = $cache->getValue(null);
        $cache->setAccessible(false);

        $this->assertArrayNotHasKey(MockPrimitiveCastModel::class, $data);
    }

    public function testResetCastCacheFlushesAll(): void
    {
        (new MockPrimitiveCastModel())->hasCast('label');
        (new MockDateCastModel())->hasCast('published_at');

        MockPrimitiveCastModel::resetCastCache();

        $ref   = new \ReflectionClass(Model::class);
        $cache = $ref->getProperty('castAttributeCache');
        $cache->setAccessible(true);
        $data = $cache->getValue(null);
        $cache->setAccessible(false);

        $this->assertEmpty($data);
    }

    public function testStringCast(): void
    {
        $model = new MockPrimitiveCastModel(['label' => 123]);

        $this->assertSame('123', $model->label);
        $this->assertIsString($model->label);
    }

    public function testIntegerCast(): void
    {
        $model = new MockPrimitiveCastModel(['score' => '42']);

        $this->assertSame(42, $model->score);
        $this->assertIsInt($model->score);
    }

    public function testFloatCast(): void
    {
        $model = new MockPrimitiveCastModel(['weight' => '3.14']);

        $this->assertSame(3.14, $model->weight);
        $this->assertIsFloat($model->weight);
    }

    public function testBooleanCastTrueValues(): void
    {
        foreach (['1', 'true', 'yes', 'on'] as $truthy) {
            $model = new MockPrimitiveCastModel(['is_active' => $truthy]);
            $this->assertTrue($model->is_active, "Expected true for value: {$truthy}");
        }
    }

    public function testBooleanCastFalseValues(): void
    {
        foreach (['0', 'false', 'no', 'off', ''] as $falsy) {
            $model = new MockPrimitiveCastModel(['is_active' => $falsy]);
            $this->assertFalse($model->is_active, "Expected false for value: {$falsy}");
        }
    }

    public function testTransformWithTypeEnumString(): void
    {
        $model = new MockTransformEnumModel(['label' => 99]);

        $this->assertSame('99', $model->label);
        $this->assertIsString($model->label);
    }

    public function testTransformWithTypeEnumInteger(): void
    {
        $model = new MockTransformEnumModel(['score' => '7']);

        $this->assertSame(7, $model->score);
        $this->assertIsInt($model->score);
    }

    public function testDateTimeCastReturnsCarbon(): void
    {
        $model = new MockDateCastModel(['published_at' => '2024-06-15 12:00:00']);

        $this->assertInstanceOf(Carbon::class, $model->published_at);
        $this->assertSame('2024-06-15', $model->published_at->format('Y-m-d'));
    }

    public function testDateCastReturnsCarbon(): void
    {
        $model = new MockDateCastModel(['birthday' => '1990-03-25']);

        $this->assertInstanceOf(Carbon::class, $model->birthday);
        $this->assertSame('1990-03-25', $model->birthday->format('Y-m-d'));
    }

    public function testTimestampCastGetReturnsCarbon(): void
    {
        // TimestampCast::get parses any stored value into a Carbon instance
        $model = new MockDateCastModel(['created_ts' => '2024-01-01 00:00:00']);

        $this->assertInstanceOf(Carbon::class, $model->created_ts);
        $this->assertSame('2024-01-01', $model->created_ts->format('Y-m-d'));
    }

    public function testTimestampCastSetConvertsDateTimeToUnixInt(): void
    {
        // TimestampCast::set serialises a DateTimeInterface to a Unix timestamp integer
        $model = new MockDateCastModel();
        $model->fill(['created_ts' => Carbon::parse('2024-01-01 00:00:00')]);

        $stored = $model->getAttributes()['created_ts'];
        $this->assertIsInt($stored);
        $this->assertSame(Carbon::parse('2024-01-01 00:00:00')->getTimestamp(), $stored);
    }

    public function testCustomDateTimeFormatCast(): void
    {
        $model = new MockCustomDateFormatModel(['invoiced_at' => '2024-06-15 09:30:00']);

        $this->assertInstanceOf(Carbon::class, $model->invoiced_at);
        $this->assertSame('2024-06-15', $model->invoiced_at->format('Y-m-d'));
    }

    public function testDateTimeCastNullPassthrough(): void
    {
        $model = new MockDateCastModel(['published_at' => null]);

        $this->assertNull($model->published_at);
    }

    public function testArrayCast(): void
    {
        $model = new MockStructuralCastModel(['options' => '{"color":"red","size":"M"}']);

        $this->assertIsArray($model->options);
        $this->assertSame('red', $model->options['color']);
        $this->assertSame('M',   $model->options['size']);
    }

    public function testJsonCast(): void
    {
        $model = new MockStructuralCastModel(['tags' => '["php","doppar"]']);

        $this->assertIsArray($model->tags);
        $this->assertContains('php',    $model->tags);
        $this->assertContains('doppar', $model->tags);
    }

    public function testObjectCast(): void
    {
        $model = new MockStructuralCastModel(['config' => '{"debug":true,"version":"1.0"}']);

        $this->assertIsObject($model->config);
        $this->assertTrue($model->config->debug);
        $this->assertSame('1.0', $model->config->version);
    }

    public function testCollectionCast(): void
    {
        $model = new MockStructuralCastModel(['items' => '["a","b","c"]']);

        $this->assertInstanceOf(Collection::class, $model->items);
        $this->assertCount(3, $model->items);
    }

    public function testDecimalCastTwoPlaces(): void
    {
        $model = new MockDecimalCastModel(['price' => '19.9999']);

        $this->assertSame(20.0, $model->price);
    }

    public function testDecimalCastFourPlaces(): void
    {
        $model = new MockDecimalCastModel(['tax_rate' => '0.12345']);

        $this->assertSame(0.1235, $model->tax_rate);
    }

    public function testDecimalCastSetRoundTrip(): void
    {
        $model = new MockDecimalCastModel();
        $model->fill(['price' => '9.9999']);

        $this->assertSame(10.0, $model->price);
    }

    public function testBackedStringEnumCast(): void
    {
        $model = new MockEnumCastModel(['status' => 'active']);

        $this->assertInstanceOf(CastTestStatus::class, $model->status);
        $this->assertSame(CastTestStatus::Active, $model->status);
    }

    public function testBackedIntEnumCast(): void
    {
        $model = new MockEnumCastModel(['priority' => '2']);

        $this->assertInstanceOf(CastTestPriority::class, $model->priority);
        $this->assertSame(CastTestPriority::Medium, $model->priority);
    }

    public function testPureUnitEnumCast(): void
    {
        $model = new MockEnumCastModel(['color' => 'Green']);

        $this->assertInstanceOf(CastTestColor::class, $model->color);
        $this->assertSame(CastTestColor::Green, $model->color);
    }

    public function testEnumSetCastSerializesBackedEnum(): void
    {
        $model = new MockEnumCastModel();
        $model->fill(['status' => CastTestStatus::Inactive]);

        $this->assertSame('inactive', $model->getAttributes()['status']);
    }

    public function testEnumSetCastSerializesUnitEnum(): void
    {
        $model = new MockEnumCastModel();
        $model->fill(['color' => CastTestColor::Blue]);

        $this->assertSame('Blue', $model->getAttributes()['color']);
    }

    public function testInvalidEnumValueThrows(): void
    {
        // __get swallows all Throwables, so test the handler directly
        $this->expectException(\ValueError::class);

        CastManager::resolve(CastTestColor::class)->get('Purple');
    }
    public function testCustomCastGetUppercases(): void
    {
        $model = new MockCustomCastModel(['name' => 'doppar']);

        $this->assertSame('DOPPAR', $model->name);
    }

    public function testCustomCastSetLowercases(): void
    {
        $model = new MockCustomCastModel();
        $model->fill(['name' => 'DOPPAR']);

        $this->assertSame('doppar', $model->getAttributes()['name']);
    }

    public function testNullValuePassesThroughAllCasts(): void
    {
        $model = new MockPrimitiveCastModel([
            'label'     => null,
            'score'     => null,
            'weight'    => null,
            'is_active' => null,
        ]);

        $this->assertNull($model->label);
        $this->assertNull($model->score);
        $this->assertNull($model->weight);
        $this->assertNull($model->is_active);
    }

    public function testGetCastAttributesAppliesCastsToAll(): void
    {
        $model = new MockPrimitiveCastModel([
            'label'     => 42,
            'score'     => '10',
            'weight'    => '1.5',
            'is_active' => '1',
        ]);

        $result = $model->getCastAttributes();

        $this->assertSame('42',  $result['label']);
        $this->assertSame(10,    $result['score']);
        $this->assertSame(1.5,   $result['weight']);
        $this->assertTrue($result['is_active']);
    }

    public function testCastManagerResolvesKnownPrimitives(): void
    {
        foreach (['string', 'integer', 'float', 'boolean', 'array', 'json', 'object', 'collection', 'datetime', 'date', 'timestamp'] as $type) {
            $handler = CastManager::resolve($type);
            $this->assertInstanceOf(CastableInterface::class, $handler);
        }
    }

    public function testCastManagerResolvesDecimalWithPrecision(): void
    {
        $handler = CastManager::resolve('decimal:3');
        $this->assertSame(1.235, $handler->get('1.2345'));
    }

    public function testCastManagerResolvesDatetimeWithFormat(): void
    {
        $handler = CastManager::resolve('datetime:Y/m/d');
        $result  = $handler->get('2024-06-15 00:00:00');
        $this->assertInstanceOf(Carbon::class, $result);
    }

    public function testCastManagerResolvesBackedEnum(): void
    {
        $handler = CastManager::resolve(CastTestStatus::class);
        $result  = $handler->get('inactive');
        $this->assertSame(CastTestStatus::Inactive, $result);
    }

    public function testCastManagerResolvesCustomCastClass(): void
    {
        $handler = CastManager::resolve(UpperCaseCast::class);
        $this->assertSame('HELLO', $handler->get('hello'));
    }

    public function testCastManagerThrowsOnUnsupportedType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CastManager::resolve('totally_unknown_cast_xyz');
    }

    public function testCastManagerFlushClearsResolvedCache(): void
    {
        CastManager::resolve('string'); // populate cache
        CastManager::flush();

        $ref      = new \ReflectionClass(CastManager::class);
        $resolved = $ref->getProperty('resolved');
        $resolved->setAccessible(true);
        $value = $resolved->getValue(null);
        $resolved->setAccessible(false);

        $this->assertEmpty($value);
    }

    public function testRequiresSetCastForComplexTypes(): void
    {
        foreach (['array', 'json', 'object', 'collection', 'datetime', 'date', 'timestamp', 'decimal:2', 'datetime:d/m/Y'] as $type) {
            $this->assertTrue(CastManager::requiresSetCast($type), "Expected true for: {$type}");
        }
    }

    public function testRequiresSetCastFalseForPrimitives(): void
    {
        foreach (['string', 'integer', 'float', 'boolean'] as $type) {
            $this->assertFalse(CastManager::requiresSetCast($type), "Expected false for: {$type}");
        }
    }

    public function testRequiresSetCastTrueForEnum(): void
    {
        $this->assertTrue(CastManager::requiresSetCast(CastTestStatus::class));
    }

    public function testRequiresSetCastTrueForCustomCastClass(): void
    {
        $this->assertTrue(CastManager::requiresSetCast(UpperCaseCast::class));
    }

    public function testStringCastRoundTripFromDatabase(): void
    {
        $id = $this->insertRecord(['label' => 'hello']);

        $model = MockPrimitiveCastModel::find($id);

        $this->assertSame('hello', $model->label);
        $this->assertIsString($model->label);
    }

    public function testIntegerCastRoundTripFromDatabase(): void
    {
        $id = $this->insertRecord(['score' => '99']);

        $model = MockPrimitiveCastModel::find($id);

        $this->assertSame(99, $model->score);
        $this->assertIsInt($model->score);
    }

    public function testBooleanCastRoundTripFromDatabase(): void
    {
        $id = $this->insertRecord(['is_active' => '1']);

        $model = MockPrimitiveCastModel::find($id);

        $this->assertTrue($model->is_active);
    }

    public function testDateTimeCastRoundTripFromDatabase(): void
    {
        $id = $this->insertRecord(['published_at' => '2024-03-10 08:30:00']);

        $model = MockDateCastModel::find($id);

        $this->assertInstanceOf(Carbon::class, $model->published_at);
        $this->assertSame('2024-03-10', $model->published_at->format('Y-m-d'));
        $this->assertSame('08:30:00',   $model->published_at->format('H:i:s'));
    }

    public function testArrayCastRoundTripFromDatabase(): void
    {
        $id = $this->insertRecord(['options' => '{"size":"L","color":"blue"}']);

        $model = MockStructuralCastModel::find($id);

        $this->assertIsArray($model->options);
        $this->assertSame('L',    $model->options['size']);
        $this->assertSame('blue', $model->options['color']);
    }

    public function testDecimalCastRoundTripFromDatabase(): void
    {
        $id = $this->insertRecord(['price' => '49.9999']);

        $model = MockDecimalCastModel::find($id);

        $this->assertSame(50.0, $model->price);
    }

    public function testEnumCastRoundTripFromDatabase(): void
    {
        $id = $this->insertRecord(['status' => 'inactive']);

        $model = MockEnumCastModel::find($id);

        $this->assertInstanceOf(CastTestStatus::class, $model->status);
        $this->assertSame(CastTestStatus::Inactive, $model->status);
    }

    public function testCollectionCastRoundTripFromDatabase(): void
    {
        $id = $this->insertRecord(['items' => '["x","y","z"]']);

        $model = MockStructuralCastModel::find($id);

        $this->assertInstanceOf(Collection::class, $model->items);
        $this->assertCount(3, $model->items);
    }
}
