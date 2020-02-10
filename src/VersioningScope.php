<?php

namespace JPNut\Versioning;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\JoinClause;

class VersioningScope implements Scope
{
    /**
     * All of the extensions to be added to the builder.
     *
     * @var array
     */
    protected array $extensions = ['AtVersion', 'AtTime'];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder                             $builder
     * @param \Illuminate\Database\Eloquent\Model|\JPNut\Versioning\Versionable $model
     *
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        //
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    protected function addAtVersion(Builder $builder)
    {
        $builder->macro(
            'atVersion',
            function (Builder $builder, $version) {
                /** @var \Illuminate\Database\Eloquent\Model|\JPNut\Versioning\Versionable $model */
                $model = $builder->getModel();

                $this->remove($builder, $builder->getModel());

                $builder
                    ->join(
                        $model->getVersionTable(),
                        function ($join) use ($model, $version) {
                            $join->on(
                                $model->getQualifiedKeyName(), '=', $model->getQualifiedVersionTableForeignKeyName()
                            )
                                ->where($model->getQualifiedVersionTableKeyName(), '=', $version);
                        }
                    )
                    ->select(
                        $model->defaultVersionSelect()
                    );

                return $builder;
            }
        );
    }

    /**
     * Remove the scope from the given Eloquent query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder                             $builder
     * @param \Illuminate\Database\Eloquent\Model|\JPNut\Versioning\Versionable $model
     *
     * @return void
     */
    public function remove(Builder $builder, Model $model)
    {
        $table = $model->getVersionTable();

        $query = $builder->getQuery();

        $query->joins = collect($query->joins)->reject(
            function ($join) use ($table) {
                return $this->isVersionJoinConstraint($join, $table);
            }
        )->values()->all();
    }

    /**
     * Determine if the given join clause is a version constraint.
     *
     * @param \Illuminate\Database\Query\JoinClause $join
     * @param string                                $table
     *
     * @return bool
     */
    protected function isVersionJoinConstraint(JoinClause $join, string $table)
    {
        return $join->type === 'inner' && $join->table === $table;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    protected function addAtTime(Builder $builder)
    {
        $builder->macro(
            'atTime',
            function (Builder $builder, Carbon $moment) {
                /** @var \Illuminate\Database\Eloquent\Model|\JPNut\Versioning\Versionable $model */
                $model = $builder->getModel();

                $this->remove($builder, $builder->getModel());

                $createdAt = $model->getQualifiedVersionTableCreatedAtName();

                $builder
                    ->join(
                        $model->getVersionTable(),
                        function ($join) use ($model, $moment, $createdAt) {
                            $join->on(
                                $model->getQualifiedKeyName(), '=', $model->getQualifiedVersionTableForeignKeyName()
                            )
                                ->where($createdAt, '<=', $moment)
                                ->orderBy($createdAt, 'desc')
                                ->limit(1);
                        }
                    )
                    ->orderBy($createdAt, 'desc')
                    ->limit(1)
                    ->select(
                        $model->defaultVersionSelect()
                    );

                return $builder;
            }
        );
    }
}
