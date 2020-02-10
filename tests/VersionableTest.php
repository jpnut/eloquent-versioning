<?php

namespace JPNut\Versioning\Tests;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use JPNut\Versioning\Tests\Models\Dummy;
use JPNut\Versioning\Tests\Models\Role;

class VersionableTest extends TestCase
{
    /** @test */
    public function it_can_create_model_with_versioned_properties()
    {
        Dummy::query()->create(
            [
                'name'  => 'Foo',
                'email' => 'foo@example.com',
                'city'  => 'Foo City',
            ]
        );

        $this->assertDatabaseHas(
            'dummies',
            [
                'id'         => 1,
                'version'    => 1,
                'name'       => 'Foo',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->assertDatabaseHas(
            'dummy_versions',
            [
                'parent_id'  => 1,
                'version'    => 1,
                'email'      => 'foo@example.com',
                'city'       => 'Foo City',
                'created_at' => now(),
            ]
        );
    }

    /** @test */
    public function it_creates_new_version_on_update()
    {
        $dummy = Dummy::query()->create(
            [
                'name'  => 'Foo',
                'email' => 'foo@example.com',
                'city'  => 'Foo City',
            ]
        );

        $created = now();

        Carbon::setTestNow($updated = now()->addHour());

        $dummy->update(
            [
                'name'  => 'Bar',
                'email' => 'bar@example.com',
            ]
        );

        $this->assertDatabaseHas(
            'dummies',
            [
                'id'         => $dummy->getKey(),
                'version'    => 2,
                'name'       => 'Bar',
                'created_at' => $created,
                'updated_at' => $updated,
            ]
        );

        $this->assertDatabaseHas(
            'dummy_versions',
            [
                'parent_id'  => $dummy->getKey(),
                'version'    => 1,
                'email'      => 'foo@example.com',
                'city'       => 'Foo City',
                'created_at' => $created,
            ]
        );

        $this->assertDatabaseHas(
            'dummy_versions',
            [
                'parent_id'  => $dummy->getKey(),
                'version'    => 2,
                'email'      => 'bar@example.com',
                'city'       => 'Foo City',
                'created_at' => $updated,
            ]
        );
    }

    /** @test */
    public function it_does_not_create_new_version_if_no_versionable_attributes_change()
    {
        $dummy = Dummy::query()->create(
            [
                'name'  => 'Foo',
                'email' => 'foo@example.com',
                'city'  => 'Foo City',
            ]
        );

        $created = now();

        Carbon::setTestNow($updated = now()->addHour());

        $dummy->update(
            [
                'name'  => 'Bar',
                'email' => 'foo@example.com',
            ]
        );

        $this->assertDatabaseHas(
            'dummies',
            [
                'id'         => $dummy->getKey(),
                'version'    => 1,
                'name'       => 'Bar',
                'created_at' => $created,
                'updated_at' => $updated,
            ]
        );

        $this->assertDatabaseHas(
            'dummy_versions',
            [
                'parent_id'  => $dummy->getKey(),
                'version'    => 1,
                'email'      => 'foo@example.com',
                'city'       => 'Foo City',
                'created_at' => $created,
            ]
        );

        $this->assertEquals(1, $this->getConnection()->table('dummy_versions')->count());
    }

    /** @test */
    public function it_can_delete_and_remove_all_versions()
    {
        $dummy = Dummy::query()->create(
            [
                'name'  => 'Foo',
                'email' => 'foo@example.com',
                'city'  => 'Foo City',
            ]
        );

        $dummy->delete();

        $this->assertEquals(0, $this->getConnection()->table('dummies')->count());
        $this->assertEquals(0, $this->getConnection()->table('dummy_versions')->count());
    }

    /** @test */
    public function it_can_soft_delete_and_keep_all_versions()
    {
        $model = new class() extends Dummy {
            use SoftDeletes;
        };

        $dummy = $model->newQuery()->create(
            [
                'name'  => 'Foo',
                'email' => 'foo@example.com',
                'city'  => 'Foo City',
            ]
        );

        $dummy->delete();

        $this->assertDatabaseHas(
            'dummies',
            [
                'id'         => $dummy->getKey(),
                'version'    => 1,
                'name'       => 'Foo',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => now(),
            ]
        );

        $this->assertDatabaseHas(
            'dummy_versions',
            [
                'parent_id'  => $dummy->getKey(),
                'version'    => 1,
                'email'      => 'foo@example.com',
                'city'       => 'Foo City',
                'created_at' => now(),
            ]
        );

        $this->assertEquals(1, $this->getConnection()->table('dummies')->count());
        $this->assertEquals(1, $this->getConnection()->table('dummy_versions')->count());
    }

    /** @test */
    public function it_can_force_delete_and_remove_all_versions()
    {
        $model = new class() extends Dummy {
            use SoftDeletes;
        };

        $dummy = $model->newQuery()->create(
            [
                'name'  => 'Foo',
                'email' => 'foo@example.com',
                'city'  => 'Foo City',
            ]
        );

        $dummy->forceDelete();

        $this->assertEquals(0, $this->getConnection()->table('dummies')->count());
        $this->assertEquals(0, $this->getConnection()->table('dummy_versions')->count());
    }

    /**
     * @test
     */
    public function it_will_retrieve_versioned_attributes()
    {
        $model = factory(Dummy::class)->create([]);

        $properties = ['id', 'version', 'name', 'email', 'city', 'created_at', 'updated_at'];

        $dummy = Dummy::query()->first();

        foreach ($properties as $property) {
            $this->assertEquals($model->{$property}, $dummy->{$property});
        }
    }

    /**
     * @test
     */
    public function it_will_retrieve_versioned_attributes_with_explicit_select()
    {
        $model = factory(Dummy::class)->create([]);

        $properties = ['id', 'version', 'name', 'email', 'city', 'created_at', 'updated_at'];

        $dummy = Dummy::query()
            ->addSelect(
                [
                    'dummies.id', 'dummies.version', 'dummies.name', 'dummy_versions.email', 'dummy_versions.city',
                    'dummies.created_at', 'dummies.updated_at',
                ]
            )
            ->first();

        foreach ($properties as $property) {
            $this->assertEquals($model->{$property}, $dummy->{$property});
        }
    }

    /**
     * @test
     */
    public function it_will_retrieve_the_latest_versioned_attributes()
    {
        $model = factory(Dummy::class)->create(
            [
                'city' => 'Foo',
            ]
        );

        $created = now();

        Carbon::setTestNow($updated = now()->addHour());

        $model->update(
            [
                'city' => 'Bar',
            ]
        );

        $dummy = Dummy::query()->first();

        $this->assertEquals(2, $dummy->version);
        $this->assertEquals('Bar', $dummy->city);
        $this->assertEquals($created->format('Y-m-d H:i:s'), $dummy->created_at);
        $this->assertEquals($updated->format('Y-m-d H:i:s'), $dummy->updated_at);
    }

    /**
     * @test
     */
    public function it_will_retrieve_the_correct_attributes_at_specific_version()
    {
        $model = factory(Dummy::class)->create(
            [
                'city' => 'Foo',
            ]
        );

        $time1 = now();

        Carbon::setTestNow($time2 = now()->addHour());

        $model->update(
            [
                'city' => 'Bar',
            ]
        );

        Carbon::setTestNow($time3 = now()->addHour());

        $model->update(
            [
                'city' => 'Baz',
            ]
        );

        $dummy1 = Dummy::atVersion(1)->find($model->id);
        $dummy2 = Dummy::atVersion(2)->find($model->id);
        $dummy3 = Dummy::atVersion(3)->find($model->id);

        $this->assertEquals('Foo', $dummy1->city);
        $this->assertEquals(1, $dummy1->version);
        $this->assertEquals($time1->format('Y-m-d H:i:s'), $dummy1->created_at);
        $this->assertEquals($time3->format('Y-m-d H:i:s'), $dummy1->updated_at);

        $this->assertEquals('Bar', $dummy2->city);
        $this->assertEquals(2, $dummy2->version);
        $this->assertEquals($time1->format('Y-m-d H:i:s'), $dummy2->created_at);
        $this->assertEquals($time3->format('Y-m-d H:i:s'), $dummy2->updated_at);

        $this->assertEquals('Baz', $dummy3->city);
        $this->assertEquals(3, $dummy3->version);
        $this->assertEquals($time1->format('Y-m-d H:i:s'), $dummy3->created_at);
        $this->assertEquals($time3->format('Y-m-d H:i:s'), $dummy3->updated_at);
    }

    /**
     * @test
     */
    public function it_will_retrieve_all_versions_from_relationship()
    {
        $model = factory(Dummy::class)->create(
            [
                'city' => 'Foo',
            ]
        );

        $model->update(
            [
                'city' => 'Bar',
            ]
        );

        $model->update(
            [
                'city' => 'Baz',
            ]
        );

        $versions = Dummy::query()->first()->versions()->orderBy('version', 'asc')->get()->toArray();

        $this->assertEquals('Foo', $versions[0]['city']);
        $this->assertEquals('Bar', $versions[1]['city']);
        $this->assertEquals('Baz', $versions[2]['city']);
    }

    /**
     * @test
     */
    public function it_will_specific_version_from_relationship()
    {
        $model = factory(Dummy::class)->create(
            [
                'city' => 'Foo',
            ]
        );

        $model->update(
            [
                'city' => 'Bar',
            ]
        );

        $model->update(
            [
                'city' => 'Baz',
            ]
        );

        $dummy = Dummy::query()->first();

        $this->assertEquals('Foo', $dummy->version(1)->city);
        $this->assertEquals('Bar', $dummy->version(2)->city);
        $this->assertEquals('Baz', $dummy->version(3)->city);
    }

    /**
     * @test
     */
    public function it_will_the_correct_version_at_specific_time()
    {
        $model = factory(Dummy::class)->create(
            [
                'city' => 'Foo',
            ]
        );

        $time1 = now();

        Carbon::setTestNow($time2 = now()->addHour());

        $model->update(
            [
                'city' => 'Bar',
            ]
        );

        Carbon::setTestNow($time3 = now()->addHour());

        $model->update(
            [
                'city' => 'Baz',
            ]
        );

        $dummy1 = Dummy::atTime($time1)->find($model->id);
        $dummy2 = Dummy::atTime($time2)->find($model->id);
        $dummy3 = Dummy::atTime($time3)->find($model->id);

        $this->assertEquals(1, $dummy1->version);
        $this->assertEquals('Foo', $dummy1->city);
        $this->assertEquals($time1->format('Y-m-d H:i:s'), $dummy1->created_at);
        $this->assertEquals($time3->format('Y-m-d H:i:s'), $dummy1->updated_at);

        $this->assertEquals(2, $dummy2->version);
        $this->assertEquals('Bar', $dummy2->city);
        $this->assertEquals($time1->format('Y-m-d H:i:s'), $dummy2->created_at);
        $this->assertEquals($time3->format('Y-m-d H:i:s'), $dummy2->updated_at);

        $this->assertEquals(3, $dummy3->version);
        $this->assertEquals('Baz', $dummy3->city);
        $this->assertEquals($time1->format('Y-m-d H:i:s'), $dummy3->created_at);
        $this->assertEquals($time3->format('Y-m-d H:i:s'), $dummy3->updated_at);
    }

    /**
     * @test
     */
    public function it_will_remove_previous_joins()
    {
        $model = factory(Dummy::class)->create(
            [
                'city' => 'Foo',
            ]
        );

        $model->update(
            [
                'city' => 'Bar',
            ]
        );

        $builder = Dummy::atVersion(1);

        // It should have one join right now
        $this->assertEquals(
            1,
            collect($builder->getQuery()->joins)->where('table', '=', 'dummy_versions')->count()
        );

        $builder->atVersion(2);

        // It should still have one join right now
        $this->assertEquals(
            1,
            collect($builder->getQuery()->joins)->where('table', '=', 'dummy_versions')->count()
        );
    }

    /**
     * @test
     */
    public function it_can_change_version()
    {
        $model = factory(Dummy::class)->create(
            [
                'city' => 'Foo',
            ]
        );

        $model->update(
            [
                'city' => 'Bar',
            ]
        );

        $model->changeVersion(1);

        $this->assertEquals(3, $model->version);

        $this->assertDatabaseHas(
            'dummies', [
                'id'      => 1,
                'version' => 3,
            ]
        );

        $this->assertDatabaseHas(
            'dummy_versions',
            [
                'parent_id' => 1,
                'version'   => 1,
                'city'      => 'Foo',
            ]
        );

        $this->assertDatabaseHas(
            'dummy_versions',
            [
                'parent_id' => 1,
                'version'   => 2,
                'city'      => 'Bar',
            ]
        );

        $this->assertDatabaseHas(
            'dummy_versions',
            [
                'parent_id' => 1,
                'version'   => 3,
                'city'      => 'Foo',
            ]
        );
    }

    /**
     * @test
     */
    public function it_can_query_versioned_attributes()
    {
        factory(Dummy::class)->create(
            [
                'city' => 'Foo',
            ]
        );
        factory(Dummy::class)->create(
            [
                'city' => 'Bar',
            ]
        );
        factory(Dummy::class)->create(
            [
                'city' => 'Baz',
            ]
        );

        $this->assertEquals(1, Dummy::query()->where('city', 'Foo')->count());
        $this->assertEquals(2, Dummy::query()->where('city', 'like', 'Ba%')->count());
    }

    /**
     * @test
     */
    public function it_can_version_relationships()
    {
        $dummy = factory(Dummy::class)->create(
            [
                'city' => 'Foo',
            ]
        );

        $this->assertEquals(1, $dummy->version);
        $this->assertNull($dummy->role);

        $role = factory(Role::class)->create(
            [
                'name' => 'test-role',
            ]
        );

        $dummy->role()->associate($role);
        $dummy->save();
        $dummy->fresh();

        $this->assertEquals(2, $dummy->version);
        $this->assertEquals($role, $dummy->role);
    }
}
