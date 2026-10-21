<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Салбарын хязгаарлалт: нэвтэрсэн хэрэглэгч Админ бол зөвхөн өөрийн салбарын төхөөрөмжүүд харагдана
 * (жагсаалт, хайлт, дэлгэрэнгүй, засах, засвар ... бүгд). Салбаргүй Админд юу ч харагдахгүй.
 * Супер админ, ажилтан (өөрийн төхөөрөмжөөр шүүгддэг), консол — хязгааргүй.
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user || $user->role !== 'admin') {
            return;
        }

        $user->branch_id
            ? $builder->where($model->qualifyColumn('branch_id'), $user->branch_id)
            : $builder->whereRaw('1 = 0');
    }
}
