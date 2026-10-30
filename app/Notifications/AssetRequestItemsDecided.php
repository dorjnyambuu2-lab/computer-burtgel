<?php

namespace App\Notifications;

use App\Models\AssetRequest;
use App\Models\AssetRequestItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Ажилтанд: захиалгын мөр шийдвэрлэгдлээ («Printer — Татгалзсан: Нөөцөд байхгүй») */
class AssetRequestItemsDecided extends Notification
{
    use Queueable;

    /** @param  list<AssetRequestItem>  $items  төлөв нь өөрчлөгдсөн мөрүүд */
    public function __construct(public AssetRequest $assetRequest, public array $items) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Таны захиалга шинэчлэгдлээ: '.$this->assetRequest->statusLabel(),
            'body' => collect($this->items)
                ->map(fn (AssetRequestItem $i) => $i->label().' — '.$i->statusLabel().($i->note ? ': '.$i->note : ''))
                ->implode("\n"),
            'url' => route('dashboard').'#my-requests',
            'kind' => 'asset_request',
        ];
    }
}
