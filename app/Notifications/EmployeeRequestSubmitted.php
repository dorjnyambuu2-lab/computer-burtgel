<?php

namespace App\Notifications;

use App\Models\AssetRequest;
use App\Models\ComputerTransfer;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Ажилтан хүсэлт илгээхэд (эвдрэл мэдээлэх, төхөөрөмж хүсэх, IT-д хүлээлгэн өгөх)
 * тухайн ажилтны салбарын Админуудад очих систем доторх мэдэгдэл.
 * Салбарт идэвхтэй Админ байхгүй бол Супер админуудад очно.
 */
class EmployeeRequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public User $employee,
        public string $title,
        public string $body,
        public string $url,
        public string $kind,
    ) {}

    /** Ажилтны салбарын Админууд (байхгүй бол Супер админууд) */
    public static function recipients(User $employee)
    {
        $admins = User::query()->where('role', 'admin')->where('is_active', true)
            ->where('branch_id', $employee->branch_id)->whereNotNull('branch_id')->get();

        return $admins->isNotEmpty() ? $admins : User::activeSuperadmins();
    }

    public static function forAssetRequest(AssetRequest $request): void
    {
        $employee = $request->user;
        $computer = $request->computer;
        $type = $request->type === 'issue' ? 'эвдрэл мэдээллээ' : 'төхөөрөмж хүслээ';

        Notify::send(self::recipients($employee), new self(
            $employee,
            $employee->name.' '.$type.($computer ? ': '.$computer->name : ''),
            trim(($request->subject ? $request->subject.' — ' : '').$request->description),
            route('requests.index', ['status' => 'pending']),
            'asset_request',
        ));
    }

    public static function forTransfer(ComputerTransfer $transfer): void
    {
        $employee = $transfer->fromUser;

        Notify::send(self::recipients($employee), new self(
            $employee,
            $employee->name.' төхөөрөмж IT-д хүлээлгэн өгөх хүсэлт илгээлээ: '.($transfer->computer?->name ?? ''),
            collect([
                $transfer->computer?->inventory_number,
                $transfer->toUser ? 'Санал болгосон хариуцагч: '.$transfer->toUser->name : null,
                $transfer->reason ? 'Шалтгаан: '.$transfer->reason : null,
            ])->filter()->implode(' · '),
            route('transfers.index'),
            'transfer',
        ));
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'kind' => $this->kind,
            'employee_id' => $this->employee->id,
        ];
    }
}
