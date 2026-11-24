<?php

namespace Tests\Unit\Model;

use Phaseolies\Database\Entity\Casts\Attributes\ToArray;
use Phaseolies\Database\Entity\Casts\Attributes\ToBoolean;
use Phaseolies\Database\Entity\Casts\Attributes\ToDateTime;
use Phaseolies\Database\Entity\Model;
use PHPUnit\Framework\TestCase;

class CastPropertyAccessModel extends Model
{
    protected $table = 'cast_property_access';

    #[ToDateTime]
    protected $timeStamps = false;

    protected $creatable = ['features', 'is_featured', 'secret', 'starts_at', 'legacy'];

    #[ToArray]
    protected mixed $features;

    #[ToBoolean]
    protected $is_featured;

    #[ToArray]
    private mixed $secret;

    #[ToDateTime]
    protected mixed $starts_at;

    #[ToArray]
    protected string $legacy;

    #[ToArray]
    protected $defaults = ['news'];

    public function defaults(): array
    {
        return $this->defaults;
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function featured(): bool
    {
        return $this->is_featured === true;
    }

    public function secretCount(): int
    {
        return count($this->secret ?? []);
    }

    public function startYear(): string
    {
        return $this->starts_at->format('Y');
    }

    public function addFeature(string $feature): void
    {
        $this->features = [...($this->features ?? []), $feature];
    }
}

class CastPropertyAccessChildModel extends CastPropertyAccessModel
{
}

/**
 * Cast attributes are declared as class properties. Reading or writing them
 * from inside the model must go through the cast, the same as from outside.
 */
class CastPropertyAccessTest extends TestCase
{
    protected function tearDown(): void
    {
        Model::resetCastCache();
        parent::tearDown();
    }

    public function testMixedAndUntypedCastsAreReadableInsideTheModel(): void
    {
        $model = new CastPropertyAccessModel(['features' => ['standings', 'teams'], 'is_featured' => 1]);

        $this->assertSame(['standings', 'teams'], $model->features);
        $this->assertTrue($model->supports('teams'));
        $this->assertFalse($model->supports('rankings'));
        $this->assertTrue($model->featured());
    }

    public function testObjectCastIsReadableInsideTheModel(): void
    {
        $model = new CastPropertyAccessModel(['starts_at' => '2026-08-15 15:00:00']);

        $this->assertSame('2026', $model->startYear());
    }

    public function testPrivateCastDeclarationIsReadableInsideTheModel(): void
    {
        $this->assertSame(3, (new CastPropertyAccessModel(['secret' => ['a', 'b', 'c']]))->secretCount());
    }

    public function testInheritedCastIsReadableInsideTheParentModel(): void
    {
        $this->assertTrue((new CastPropertyAccessChildModel(['features' => ['news']]))->supports('news'));
    }

    public function testWritingInsideTheModelUpdatesTheAttribute(): void
    {
        $model = new CastPropertyAccessModel(['features' => ['news']]);

        $model->addFeature('teams');

        $this->assertSame(['news', 'teams'], $model->features);
        $this->assertArrayHasKey('features', $model->getDirtyAttributes());
    }

    public function testCastAttributeIsReadableInsideTheModelAfterUnserialize(): void
    {
        $model = unserialize(serialize(new CastPropertyAccessModel(['features' => ['news']])));

        $this->assertTrue($model->supports('news'));
    }

    public function testStringTypedCastStillWorksFromOutsideTheModel(): void
    {
        $model = new CastPropertyAccessModel(['legacy' => ['a' => 1]]);

        $this->assertSame(['a' => 1], $model->legacy);
    }

    public function testModelSettingWithACastAttributeKeepsItsValue(): void
    {
        $model = new CastPropertyAccessModel();

        $this->assertFalse($model->usesTimestamps());
    }

    public function testPropertyWithADefaultValueIsNotTreatedAsACastMarker(): void
    {
        $this->assertSame(['news'], (new CastPropertyAccessModel())->defaults());
    }
}
