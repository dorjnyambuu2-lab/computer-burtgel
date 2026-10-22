<?php

namespace App\Support;

use App\Models\Computer;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Системд хандсан компьютерийн дотоод сүлжээний IP, MAC.
 *  - Сервер дээрээ (127.0.0.1): gateway-тэй, идэвхтэй адаптерийн IP, MAC
 *  - Дотоод сүлжээний өөр компьютер: хүсэлтийн IP, MAC-г серверийн ARP хүснэгтээс
 */
class ClientNetwork
{
    /** Компьютер гэж тооцох ангилал (IP, MAC-тай): процессор, нөүтбүк, ангилалгүй */
    private const COMPUTER_CATEGORIES = ['processor', 'laptop'];

    /** @return array{ip: ?string, mac: ?string, local: bool} */
    public static function detect(Request $request): array
    {
        $ip = (string) $request->ip();
        $local = in_array($ip, ['127.0.0.1', '::1'], true);
        $mac = null;

        if ($local) {
            [$ip, $mac] = self::localAdapter();
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $mac = self::macFromArp($ip);
        }

        return [
            'ip' => filter_var($ip, FILTER_VALIDATE_IP) && ! in_array($ip, ['127.0.0.1', '::1'], true) ? $ip : null,
            'mac' => $mac,
            'local' => $local,
        ];
    }

    /**
     * Ажилтан өөрийн компьютероос нэвтрэх, акт баталгаажуулах үед тэр компьютерийн IP, MAC-г бүртгэлд автоматаар бичнэ.
     *  - MAC нь таарсан компьютер байвал IP-г нь шинэчилнэ
     *  - Ажилтанд ганц компьютер байгаа бөгөөд MAC нь хоосон бол IP, MAC-г бөглөнө
     *  - Аль компьютер болохыг тодорхойлох боломжгүй (хэд хэдэн, эсвэл өөр MAC-тай) бол юу ч өөрчлөхгүй
     * Сервер дээрээс (127.0.0.1) нэвтэрсэн бол алгасна — тэр нь ажилтны компьютер биш.
     *
     * @return Computer|null шинэчилсэн компьютер
     */
    public static function syncForEmployee(User $user, Request $request): ?Computer
    {
        if ($user->role !== 'employee') {
            return null;
        }

        $net = self::detect($request);
        if ($net['local'] || ! $net['ip']) {
            return null;
        }

        $devices = Computer::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('assigned_user_id', $user->id)
            ->where(fn ($q) => $q->whereIn('category', self::COMPUTER_CATEGORIES)->orWhereNull('category'))
            ->get();

        $target = $net['mac'] ? $devices->first(fn ($c) => strtoupper((string) $c->mac_address) === $net['mac']) : null;
        // MAC нь өөр компьютерт бүртгэлтэй бол (хамт ажиллагчийн компьютероос нэвтэрсэн) энэ ажилтных биш
        $macTaken = $net['mac'] && Computer::withoutGlobalScopes()->whereNull('deleted_at')->where('mac_address', $net['mac'])->exists();
        if (! $target && ! $macTaken && $devices->count() === 1 && blank($devices->first()->mac_address)) {
            $target = $devices->first();
        }
        if (! $target) {
            return null;
        }

        $target->ip_address = $net['ip'];
        if ($net['mac'] && blank($target->mac_address)) {
            $target->mac_address = $net['mac'];
        }
        $target->network_updated_at = now();
        $target->save(); // IP, MAC өөрчлөгдсөн бол үйлдлийн түүхэнд бичигдэнэ

        return $target;
    }

    /** Сервер компьютерийн дотоод сүлжээнд холбогдсон (gateway-тэй) адаптер: [IP, MAC] */
    private static function localAdapter(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [null, null];
        }
        // WMI: gateway-тэй, идэвхтэй адаптер. -EncodedCommand нь хашилт, тусгай тэмдэгтийг гэмтээхгүй
        $ps = '$a = Get-CimInstance Win32_NetworkAdapterConfiguration -Filter "IPEnabled=True" | Where-Object { $_.DefaultIPGateway } | Select-Object -First 1; '
            .'if ($a) { ($a.IPAddress | Where-Object { $_ -match "^\d+\.\d+\.\d+\.\d+$" } | Select-Object -First 1) + "|" + $a.MACAddress }';
        $encoded = base64_encode(mb_convert_encoding($ps, 'UTF-16LE', 'UTF-8'));
        $out = trim((string) shell_exec('powershell -NoProfile -NonInteractive -EncodedCommand '.$encoded.' 2>NUL'));
        [$ip, $mac] = array_pad(explode('|', $out, 2), 2, null);

        return [filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null, self::cleanMac($mac)];
    }

    /** Дотоод сүлжээний компьютерийн MAC-г ARP хүснэгтээс (тухайн IP саяхан холбогдсон тул хүснэгтэд байна) */
    private static function macFromArp(string $ip): ?string
    {
        $out = (string) shell_exec('arp -a '.escapeshellarg($ip));
        if (preg_match('/'.preg_quote($ip, '/').'\s+([0-9a-f]{2}([-:][0-9a-f]{2}){5})/i', $out, $m)) {
            return self::cleanMac($m[1]);
        }

        return null;
    }

    private static function cleanMac(?string $mac): ?string
    {
        $mac = strtoupper(str_replace('-', ':', trim((string) $mac)));

        return preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac) && $mac !== 'FF:FF:FF:FF:FF:FF' ? $mac : null;
    }
}
