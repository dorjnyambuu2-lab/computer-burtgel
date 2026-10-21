<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Жагсаалтын дэс дугаараар (#1, #2 ...) хайх. Дэс дугаар нь тухайн шүүлтүүр, эрэмбэлэлтээрх мөрийн байр тул
 * хайлтанд «#12» (эсвэл «12») гэж бичвэл яг тэр байрын мөрийг #12 дугаартайгаар нь харуулна.
 */
class RowNumber
{
    /** «#12», «№12», «12» → 12; бусад үед null (энгийн текст хайлт) */
    public static function parse(?string $term): ?int
    {
        return preg_match('/^\s*[#№]?\s*(\d{1,7})\s*$/u', (string) $term, $m) && (int) $m[1] > 0 ? (int) $m[1] : null;
    }

    /**
     * Дугаараар хайсан бол тэр мөрийг (дугаар нь хэвээр), үгүй бол энгийн хуудаслалт.
     * Үр дүнгийн firstItem() нь хайсан дугаар тул view-ийн «firstItem() + $loop->index» дугаарлалт өөрчлөгдөхгүй.
     */
    public static function paginate(Builder $query, Request $request, int $perPage, ?int $number): LengthAwarePaginator
    {
        if ($number === null) {
            return $query->paginate($perPage)->withQueryString();
        }

        $item = (clone $query)->skip($number - 1)->take(1)->get();

        $paginator = new class($item, $item->count(), $perPage, 1, ['path' => $request->url(), 'query' => $request->query()]) extends LengthAwarePaginator {
            public int $rowOffset = 0;

            public function firstItem(): ?int
            {
                return $this->count() ? $this->rowOffset + 1 : null;
            }
        };
        $paginator->rowOffset = $number - 1;

        return $paginator;
    }
}
