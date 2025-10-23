<?php

namespace Tests\Unit;

use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Builder;
use Phaseolies\Database\Database;
use Phaseolies\Support\Collection;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

class ModelTest extends TestCase
{
    private $pdo;
    private $pdoStatement;

    protected function setUp(): void
    {
        $this->pdoStatement = $this->createMock(PDOStatement::class);
        $this->pdo = $this->createMock(PDO::class);

        $databaseMock = $this->createMock(Database::class);
        $databaseMock->method('getPdoInstance')
            ->willReturn($this->pdo);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
    }

    public function testModelInitialization()
    {
        $model = new TestModel(['name' => 'John', 'email' => 'john@example.com']);

        $this->assertEquals('John', $model->name);
        $this->assertEquals('john@example.com', $model->email);
        $this->assertEquals('testmodel', $model->getTable());
        $this->assertEquals('id', $model->getKeyName());
    }

    public function testTableNameInference()
    {
        $model = new TestModel();
        $this->assertEquals('testmodel', $model->getTable());

        $model2 = new UserModel();
        $this->assertEquals('usermodel', $model2->getTable());
    }

    public function testCustomTableName()
    {
        $model = new CustomTableModel();
        $this->assertEquals('custom_table', $model->getTable());
    }

    public function testSetTable()
    {
        $model = new TestModel();
        $model->setTable('custom_table');

        $this->assertEquals('custom_table', $model->getTable());
    }

    public function testAttributeAccess()
    {
        $model = new TestModel([
            'name' => 'Jane',
            'age' => 25
        ]);

        // Array access
        $this->assertEquals('Jane', $model['name']);
        $this->assertEquals(25, $model['age']);

        // Object access
        $this->assertEquals('Jane', $model->name);
        $this->assertEquals(25, $model->age);

        // Check existence
        $this->assertTrue(isset($model['name']));
        $this->assertFalse(isset($model['nonexistent']));
    }

    public function testAttributeMutation()
    {
        $model = new TestModel();

        // Test string sanitization
        $model->name = '  John  ';
        $this->assertEquals('John', $model->name);

        // Test empty string becomes null
        $model->email = '   ';
        $this->assertNull($model->email);
    }

    public function testMassAssignment()
    {
        $model = new TestModel();
        $model->fill([
            'name' => 'John',
            'email' => 'john@example.com',
            'age' => 30
        ]);

        $this->assertEquals('John', $model->name);
        $this->assertEquals('john@example.com', $model->email);
        $this->assertEquals(30, $model->age);
    }

    public function testPrimaryKeyMethods()
    {
        $model = new TestModel(['id' => 123, 'name' => 'Test']);

        $this->assertEquals('id', $model->getKeyName());
        $this->assertEquals(123, $model->getKey());
    }

    public function testUnexposableAttributes()
    {
        $model = new TestModel([
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret'
        ]);

        $visible = $model->makeVisible();

        $this->assertArrayHasKey('name', $visible);
        $this->assertArrayHasKey('email', $visible);
        $this->assertArrayNotHasKey('password', $visible);
    }

    public function testMakeHidden()
    {
        $model = new TestModel(['name' => 'John', 'email' => 'john@example.com']);

        $model->makeHidden(['email']);
        $visible = $model->makeVisible();

        $this->assertArrayHasKey('name', $visible);
        $this->assertArrayNotHasKey('email', $visible);
    }

    public function testArrayAccess()
    {
        $model = new TestModel();

        // Test offsetSet and offsetGet
        $model['name'] = 'John';
        $this->assertEquals('John', $model['name']);

        // Test offsetExists
        $this->assertTrue(isset($model['name']));
        $this->assertFalse(isset($model['nonexistent']));

        // Test offsetUnset
        unset($model['name']);
        $this->assertFalse(isset($model['name']));
    }

    public function testJsonSerialization()
    {
        $model = new TestModel([
            'name' => 'John',
            'email' => 'john@example.com'
        ]);

        $array = $model->jsonSerialize();
        $this->assertIsArray($array);
        $this->assertEquals('John', $array['name']);
        $this->assertEquals('john@example.com', $array['email']);
    }

    public function testToString()
    {
        $model = new TestModel(['name' => 'John']);
        $string = (string)$model;

        $this->assertJson($string);
        $data = json_decode($string, true);
        $this->assertEquals('John', $data['name']);
    }

    // public function testNewQuery()
    // {
    //     $model = new TestModel();
    //     $query = $model->newQuery();

    //     $this->assertInstanceOf(Builder::class, $query);
    // }

    // public function testConnection()
    // {
    //     $model = new TestModel();
    //     $connection = $model->getConnection();

    //     $this->assertInstanceOf(PDO::class, $connection);
    // }

    // public function testCustomConnection()
    // {
    //     $model = TestModel::connection('custom_connection');

    //     $this->assertInstanceOf(Builder::class, $model);
    // }

    public function testGetAttributes()
    {
        $attributes = [
            'name' => 'John',
            'email' => 'john@example.com'
        ];

        $model = new TestModel($attributes);

        $this->assertEquals($attributes, $model->getAttributes());
    }

    public function testOriginalAttributes()
    {
        $model = new TestModel(['name' => 'Original']);

        $original = $model->getOriginalAttributes();
        $this->assertEquals('Original', $original['name']);

        $this->assertEquals('Original', $model->getOriginal('name'));
        $this->assertNull($model->getOriginal('nonexistent'));
        $this->assertEquals('default', $model->getOriginal('nonexistent', 'default'));
    }

    public function testDirtyAttributes()
    {
        $model = new TestModel(['name' => 'Original', 'email' => 'original@example.com']);

        // Initially should not be dirty
        $this->assertFalse($model->isDirtyAttr('name'));

        // Change attribute
        $model->name = 'Modified';

        // Should be dirty now
        $this->assertTrue($model->isDirtyAttr('name'));
        $this->assertFalse($model->isDirtyAttr('email'));
    }

    public function testRouteKeyName()
    {
        $model = new TestModel();
        $this->assertEquals('id', $model->getRouteKeyName());
    }

    public function testAuthKeyName()
    {
        $model = new TestModel();
        $this->assertEquals('email', $model->getAuthKeyName());
    }

    public function testTimestampsUsage()
    {
        $model = new TestModel();
        $this->assertTrue($model->usesTimestamps());

        $modelWithoutTimestamps = new ModelWithoutTimestamps();
        $this->assertFalse($modelWithoutTimestamps->usesTimestamps());
    }

    public function testRelationships()
    {
        $model = new TestModel(['id' => 1]);

        // Test setting and getting relations
        $relatedModel = new TestModel(['id' => 2, 'name' => 'Related']);
        $model->setRelation('related', $relatedModel);

        $this->assertTrue($model->relationLoaded('related'));
        $this->assertFalse($model->relationLoaded('nonexistent'));
        $this->assertEquals($relatedModel, $model->getRelation('related'));
    }

    public function testGetRelations()
    {
        $model = new TestModel();
        $relation1 = new TestModel();
        $relation2 = new TestModel();

        $model->setRelation('relation1', $relation1);
        $model->setRelation('relation2', $relation2);

        $relations = $model->getRelations();

        $this->assertArrayHasKey('relation1', $relations);
        $this->assertArrayHasKey('relation2', $relations);
        $this->assertEquals($relation1, $relations['relation1']);
    }

    public function testSetRelations()
    {
        $model = new TestModel();
        $relations = [
            'relation1' => new TestModel(),
            'relation2' => new TestModel()
        ];

        $model->setRelations($relations);

        $this->assertEquals($relations, $model->getRelations());
    }

    public function testToArray()
    {
        $model = new TestModel([
            'name' => 'John',
            'email' => 'john@example.com'
        ]);

        $relatedModel = new TestModel(['name' => 'Related']);
        $model->setRelation('related', $relatedModel);

        $array = $model->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('John', $array['name']);
        $this->assertEquals('john@example.com', $array['email']);
        $this->assertIsArray($array['related']);
        $this->assertEquals('Related', $array['related']['name']);
    }

    // public function testLinkOneRelationship()
    // {
    //     $model = new TestModel(['id' => 1]);
    //     $builder = $model->linkOne(RelatedModel::class, 'test_model_id', 'id');

    //     $this->assertInstanceOf(Builder::class, $builder);
    //     $this->assertEquals('linkOne', $model->getLastRelationType());
    //     $this->assertEquals(RelatedModel::class, $model->getLastRelatedModel());
    //     $this->assertEquals('test_model_id', $model->getLastForeignKey());
    //     $this->assertEquals('id', $model->getLastLocalKey());
    // }

    // public function testLinkManyRelationship()
    // {
    //     $model = new TestModel(['id' => 1]);
    //     $builder = $model->linkMany(RelatedModel::class, 'test_model_id', 'id');

    //     $this->assertInstanceOf(Builder::class, $builder);
    //     $this->assertEquals('linkMany', $model->getLastRelationType());
    // }

    // public function testBindToRelationship()
    // {
    //     $model = new TestModel(['id' => 1]);
    //     $builder = $model->bindTo(RelatedModel::class, 'test_model_id', 'id');

    //     $this->assertInstanceOf(Builder::class, $builder);
    //     $this->assertEquals('bindTo', $model->getLastRelationType());
    // }
}

class TestModel extends Model
{
    protected $unexposable = ['password'];
    protected $timeStamps = true;
}

class UserModel extends Model
{
    // Uses default table name inference
}

class CustomTableModel extends Model
{
    protected $table = 'custom_table';
}

class ModelWithoutTimestamps extends Model
{
    protected $timeStamps = false;
}

class RelatedModel extends Model
{
    protected $table = 'related_models';
}
