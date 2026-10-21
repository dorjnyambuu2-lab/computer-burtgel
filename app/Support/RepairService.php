<?php

namespace App\Support;

use App\Enums\ComputerStatus;
use App\Enums\RepairStage;
use App\Models\AssetRequest;
use App\Models\Computer;
use App\Models\ComputerEvent;
use App\Models\User;
use App\Notifications\RepairStatusChanged;
use Illuminate\Support\Facades\DB;

/**
 * Засварын дараалал: засварт оруулах, явц шинэчлэх, дуусгах, дараалал өөрчлөх.
 * Үйлдэл бүр түүхэнд бичигдэж, холбогдох хүмүүст мэдэгдэл (систем + имэйл) очно.
 */
class RepairService
{
    /** $cost: засварын зардал (₮) — оношилгоо, сэлбэг, ажлын хөлс гэх мэт; дараа нь явц шинэчлэхэд нэмж оруулж болно */
    public function start(Computer $computer, User $actor, string $note, ?string $cost = null): void
    {
        DB::transaction(function () use ($computer, $actor, $note, $cost) {
            $computer->status = ComputerStatus::InRepair; // model нь дарааллын төгсгөлд оруулна
            $computer->save();

            $this->log($computer, $actor, 'repair', $note, $cost);
        });

        $this->notify($computer, $actor, 'queued', $note);
    }

    public function updateStage(Computer $computer, User $actor, RepairStage $stage, ?string $note, ?string $cost = null): void
    {
        $previous = $computer->repair_stage;

        DB::transaction(function () use ($computer, $actor, $stage, $note, $previous, $cost) {
            $computer->repair_stage = $stage;
            $computer->save();

            $text = ($previous !== $stage ? 'Явц: '.$stage->label().'. ' : '').($note ?? '');
            $this->log($computer, $actor, 'repair_update', $text, $cost);
        });

        $this->notify($computer, $actor, 'stage', $note);
    }

    /** Засвар дууссан: хариуцагчтай бол «Ашиглагдаж байгаа», үгүй бол «Агуулахад» */
    public function complete(Computer $computer, User $actor, ?string $note, ?string $cost = null): void
    {
        DB::transaction(function () use ($computer, $actor, $note, $cost) {
            $computer->status = $computer->assigned_user_id ? ComputerStatus::Active : ComputerStatus::InStorage;
            $computer->save(); // model нь дарааллаас гаргана

            $this->log($computer, $actor, 'repaired', $note, $cost);
            $this->renumber();

            // Энэ компьютерт холбоотой нээлттэй эвдрэлийн хүсэлтүүдийг хаана
            AssetRequest::query()
                ->where('computer_id', $computer->id)
                ->where('type', 'issue')
                ->whereIn('status', ['pending', 'in_progress'])
                ->update(['status' => 'done', 'handled_by' => $actor->id, 'updated_at' => now()]);
        });

        $this->notify($computer, $actor, 'done', $note);
    }

    /** Дараалалд нэг байр дээш (-1) эсвэл доош (+1) шилжүүлнэ */
    public function move(Computer $computer, int $offset): bool
    {
        return DB::transaction(function () use ($computer, $offset) {
            $ids = Computer::query()->repairQueue()->lockForUpdate()->pluck('id')->all();
            $from = array_search($computer->id, $ids, true);
            $to = $from === false ? false : $from + $offset;

            if ($to === false || $to < 0 || $to >= count($ids)) {
                return false;
            }

            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
            $this->savePositions($ids);

            return true;
        });
    }

    /** Байрыг 1, 2, 3 ... болгож цэгцэлнэ (дундаас гарсан компьютерийн зай арилна) */
    public function renumber(): void
    {
        $this->savePositions(Computer::query()->repairQueue()->pluck('id')->all());
    }

    private function savePositions(array $ids): void
    {
        // Үйлдлийн түүхэнд олон мөр бичигдэж, updated_at өөрчлөгдөхгүйн тулд event-гүй шууд шинэчилнэ
        foreach (array_values($ids) as $i => $id) {
            Computer::query()->whereKey($id)->toBase()->update(['repair_position' => $i + 1]);
        }
    }

    private function log(Computer $computer, User $actor, string $type, ?string $note, ?string $cost = null): void
    {
        ComputerEvent::create([
            'computer_id' => $computer->id,
            'actor_id' => $actor->id,
            'user_id' => $computer->assigned_user_id,
            'type' => $type,
            'note' => filled($note) ? trim($note) : null,
            'cost' => filled($cost) && (float) $cost > 0 ? $cost : null,
        ]);
    }

    /**
     * Хүлээн авагчид: компьютерийн хариуцагч, энэ компьютерт эвдрэл мэдээлсэн ажилтнууд,
     * бусад админ / супер админ. Үйлдлийг хийсэн хүн өөртөө мэдэгдэл авахгүй.
     */
    private function notify(Computer $computer, User $actor, string $kind, ?string $note): void
    {
        $computer->refresh();

        $reporterIds = AssetRequest::query()
            ->where('computer_id', $computer->id)
            ->where('type', 'issue')
            ->where('updated_at', '>=', now()->subDays(60))
            ->pluck('user_id');

        $recipients = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($computer, $reporterIds) {
                $q->whereIn('id', $reporterIds->push($computer->assigned_user_id)->filter())
                  ->orWhere('role', 'superadmin')
                  // Админ: зөвхөн тухайн төхөөрөмжийн салбарын админ
                  ->orWhere(fn ($q) => $q->where('role', 'admin')->where('branch_id', $computer->branch_id));
            })
            ->whereKeyNot($actor->id)
            ->get();

        Notify::send($recipients, new RepairStatusChanged($computer, $kind, $note));
    }
}
