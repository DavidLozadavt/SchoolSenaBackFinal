<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'idPermissionPadre',
    ];

    /**
     * ──► Parent permission (self‑referencing)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            Permission::class,
            'idPermissionPadre',
            'id'
        );
    }

    /**
     * ◄── Children of this permission
     */
    public function children(): HasMany
    {
        return $this->hasMany(
            Permission::class,
            'idPermissionPadre',
            'id'
        );
    }

    /**
     * Recursively load children for tree building.
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * Collect all descendant IDs to detect cycles.
     *
     * @return array<int>
     */
    public function getDescendantIds(): array
    {
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getDescendantIds());
        }
        return $ids;
    }
}
