<?php

namespace App\Http\Controllers;

use App\Enums\ComputerType;
use App\Enums\EquipmentCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Компьютерийн мэдээллийг автоматаар уншиж бүртгэх.
 *  - script(): тухайн компьютер дээр ажиллуулах .bat файл (Windows). Үйлдвэрлэгч, загвар, серийн дугаар,
 *    CPU, RAM, диск, ҮС, IP, MAC-г уншаад бөглөгдсөн бүртгэлийн формыг хөтөч дээр нээнэ.
 *  - normalize(): скриптээс ирсэн утгыг формд тохирох хэлбэрт оруулна (ComputerController::create ашиглана).
 */
class ComputerDetectController extends Controller
{
    /** Скриптээс хүлээн авах талбарууд */
    public const FIELDS = ['name', 'type', 'brand', 'model', 'serial_number', 'cpu', 'ram_gb', 'storage', 'os', 'ip_address', 'mac_address'];

    /** Холбогдсон төхөөрөмж бүрийн (дэлгэц, printer ...) талбарууд. specs нь тэмдэглэлд хадгалагдана */
    public const DEVICE_FIELDS = ['brand', 'model', 'serial_number', 'cpu', 'ram_gb', 'storage', 'os', 'ip_address', 'mac_address', 'specs'];

    /** Дэлгэцийн EDID-д үйлдвэрлэгч 3 үсгийн кодоор бичигддэг */
    private const EDID_CODES = [
        'DEL' => 'Dell', 'HWP' => 'HP', 'HPN' => 'HP', 'LEN' => 'Lenovo', 'SAM' => 'Samsung', 'SEC' => 'Samsung', 'ACR' => 'Acer',
        'AUS' => 'Asus', 'ACI' => 'Asus', 'GSM' => 'LG', 'LGD' => 'LG', 'PHL' => 'Philips', 'BNQ' => 'BenQ', 'AOC' => 'AOC',
        'VSC' => 'ViewSonic', 'MSI' => 'MSI', 'IVM' => 'Iiyama', 'NEC' => 'NEC', 'SNY' => 'Sony', 'HKC' => 'HKC', 'XMI' => 'Xiaomi',
        'HUA' => 'Huawei', 'APP' => 'Apple', 'GBT' => 'Gigabyte', 'BOE' => 'BOE', 'AUO' => 'AUO', 'CMN' => 'Innolux',
    ];

    /** Үйлдвэрлэгчийн нэрийг нэг хэлбэрт оруулах (BIOS-д "Dell Inc.", "LENOVO", "Hewlett-Packard" гэх мэт байдаг) */
    private const BRANDS = [
        'dell' => 'Dell', 'hewlett' => 'HP', 'hp' => 'HP', 'lenovo' => 'Lenovo', 'asus' => 'Asus', 'acer' => 'Acer',
        'apple' => 'Apple', 'micro-star' => 'MSI', 'msi' => 'MSI', 'microsoft' => 'Microsoft', 'samsung' => 'Samsung',
        'huawei' => 'Huawei', 'xiaomi' => 'Xiaomi', 'timi' => 'Xiaomi', 'gigabyte' => 'Gigabyte', 'fujitsu' => 'Fujitsu',
        'toshiba' => 'Toshiba', 'dynabook' => 'Dynabook', 'intel' => 'Intel', 'vmware' => 'VMware', 'innotek' => 'VirtualBox',
    ];

    /** Үйлдвэрлэгч бөглөөгүй үед BIOS-д бичигддэг утгагүй утгууд */
    private const JUNK = ['to be filled by o.e.m.', 'default string', 'system serial number', 'system product name',
        'system manufacturer', 'not applicable', 'none', 'o.e.m.', 'oem', '0', '00000000', '123456789', 'chassis serial number'];

    public function script(Request $request): Response
    {
        // Скриптээр уншсан мэдээллээр «Шинэ компьютер бүртгэх» формыг бөглөж нээнэ
        $url = route('computers.create');

        // localhost-оор татсан бол өөр компьютерээс хандахын тулд серверийн дотоод сүлжээний IP-г тавина
        $host = parse_url($url, PHP_URL_HOST);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            // UDP "холболт" нь пакет илгээхгүй, зөвхөн гарах сүлжээний адаптерын IP-г олно (VirtualBox гэх мэт адаптерыг алгасна)
            $sock = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);
            $lan = $sock ? explode(':', (string) stream_socket_get_name($sock, false))[0] : '';
            if ($sock) {
                fclose($sock);
            }
            if ($lan && ! str_starts_with($lan, '127.') && filter_var($lan, FILTER_VALIDATE_IP)) {
                $url = preg_replace('#//(localhost|127\.0\.0\.1|\[::1\])#', '//'.$lan, $url, 1);
            }
        }

        $ps = str_replace('__CREATE_URL__', $url, self::POWERSHELL);

        // .bat нь өөрийнхөө доторх PowerShell хэсгийг уншиж ажиллуулна (ExecutionPolicy тохиргоо хэрэггүй)
        $bat = "@echo off\r\n"
            ."powershell -NoProfile -ExecutionPolicy Bypass -Command \"\$s=[IO.File]::ReadAllText('%~f0'); \$m='::'+'PSSTART'; iex \$s.Substring(\$s.IndexOf(\$m)+\$m.Length)\"\r\n"
            ."pause\r\n"
            ."exit /b\r\n"
            ."::PSSTART\r\n"
            .str_replace("\n", "\r\n", str_replace("\r\n", "\n", $ps));

        return response($bat, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="computer-burtgel.bat"',
        ]);
    }

    /**
     * Скриптээс ирсэн утгыг цэвэрлэх. Хоосон/утгагүй утгыг хасна.
     *
     * @return array<string, string|int>
     */
    public static function normalize(array $input, array $fields = self::FIELDS): array
    {
        $out = [];
        foreach ($fields as $f) {
            $v = trim(preg_replace('/\s+/u', ' ', (string) ($input[$f] ?? '')));
            if ($v === '' || in_array(mb_strtolower($v), self::JUNK, true)) {
                continue;
            }
            $out[$f] = mb_substr($v, 0, 255);
        }

        if (isset($out['brand'])) {
            $out['brand'] = self::EDID_CODES[strtoupper($out['brand'])] ?? $out['brand'];
            $lower = mb_strtolower($out['brand']);
            foreach (self::BRANDS as $needle => $name) {
                if (str_starts_with($lower, $needle)) {
                    $out['brand'] = $name;
                    break;
                }
            }
            // Загварт үйлдвэрлэгчийн нэр давхардсан бол хасна: "Dell G15 5515" → "G15 5515"
            if (isset($out['model']) && stripos($out['model'], $out['brand'].' ') === 0) {
                $out['model'] = trim(substr($out['model'], strlen($out['brand'])));
            }
        }

        // "Intel(R) Core(TM) i5-12400 CPU @ 2.50GHz" → "Intel Core i5-12400"
        if (isset($out['cpu'])) {
            $cpu = preg_replace(['/\((R|TM|tm|r)\)/', '/\s+CPU\b/i', '/\s*@.*$/', '/\s+\d+-Core Processor$/i', '/\s+with Radeon.*$/i'], '', $out['cpu']);
            $out['cpu'] = trim(preg_replace('/\s+/', ' ', $cpu));
        }

        // RAM: «16» эсвэл модулиудаар «8GB + 8GB»
        if (isset($out['ram_gb'])) {
            [$total, $modules] = \App\Support\SpecSuggestions::ram($out['ram_gb']) ?? [0, null];
            if ($total > 0) {
                $out['ram_gb'] = $modules ?? (string) $total;
            } else {
                unset($out['ram_gb']);
            }
        }

        if (isset($out['os'])) {
            $out['os'] = trim(preg_replace('/^Microsoft\s+/i', '', $out['os']));
        }

        if (isset($out['type']) && ! ComputerType::tryFrom($out['type'])) {
            unset($out['type']);
        }

        if (isset($out['ip_address']) && ! filter_var($out['ip_address'], FILTER_VALIDATE_IP)) {
            unset($out['ip_address']);
        }

        if (isset($out['mac_address'])) {
            $out['mac_address'] = strtoupper(str_replace('-', ':', $out['mac_address']));
            if (! preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $out['mac_address'])) {
                unset($out['mac_address']);
            }
        }

        return $out;
    }

    /**
     * Холбогдсон төхөөрөмжүүд (devices[i][category], devices[i][brand] ...). Ангилал буруу эсвэл хоосон мөрийг хасна.
     *
     * @return list<array<string, string|int>>
     */
    public static function normalizeDevices(array $devices): array
    {
        $out = [];
        foreach (array_slice(array_values($devices), 0, 20) as $device) {
            if (! is_array($device) || ! EquipmentCategory::tryFrom((string) ($device['category'] ?? ''))) {
                continue;
            }
            $clean = self::normalize($device, self::DEVICE_FIELDS);
            if ($clean !== []) {
                $out[] = ['category' => $device['category']] + $clean;
            }
        }

        return $out;
    }

    private const POWERSHELL = <<<'PS'

$ErrorActionPreference = 'SilentlyContinue'
$createUrl = '__CREATE_URL__'
Write-Host ''
Write-Host '  Компьютерийн мэдээллийг уншиж байна...' -ForegroundColor Cyan

$cs   = Get-CimInstance Win32_ComputerSystem
$csp  = Get-CimInstance Win32_ComputerSystemProduct
$bios = Get-CimInstance Win32_BIOS
$os   = Get-CimInstance Win32_OperatingSystem
$enc  = Get-CimInstance Win32_SystemEnclosure | Select-Object -First 1

# Загвар: Lenovo-д жинхэнэ нэр нь Version талбарт байдаг (ж: "ThinkPad T14 Gen 2")
$brand = "$($cs.Manufacturer)".Trim()
$model = "$($cs.Model)".Trim()
if ($brand -match 'lenovo' -and "$($csp.Version)".Trim()) { $model = "$($csp.Version)".Trim() }

# RAM: суусан модулиуд (GB): нэг бол «16», олон бол «8GB + 8GB»
$mods = @(Get-CimInstance Win32_PhysicalMemory | Where-Object { $_.Capacity } | ForEach-Object { [math]::Round($_.Capacity / 1GB) })
if ($mods.Count -gt 1) { $ram = ($mods | ForEach-Object { "$($_)GB" }) -join ' + ' }
elseif ($mods.Count -eq 1) { $ram = $mods[0] }
else { $ram = [math]::Round($cs.TotalPhysicalMemory / 1GB) }

# Диск: SSD/HDD/NVMe + стандарт хэмжээ (USB, SD картыг тооцохгүй)
function Format-Size([double]$bytes) {
    $gb = $bytes / 1e9
    $std = 64,120,128,240,250,256,320,480,500,512,640,750,960,1000,2000,3000,4000,6000,8000
    $n = ($std | Sort-Object { [math]::Abs([math]::Log($_ / $gb)) } | Select-Object -First 1)
    if ($n -ge 1000) { return "$($n / 1000)TB" } else { return "${n}GB" }
}
$disks = @()
Get-CimInstance -Namespace root/Microsoft/Windows/Storage -ClassName MSFT_PhysicalDisk |
    Where-Object { $_.BusType -notin 7, 12, 13 } | ForEach-Object {
        $t = if ($_.BusType -eq 17) { 'NVMe SSD' } elseif ($_.MediaType -eq 4) { 'SSD' } elseif ($_.MediaType -eq 3) { 'HDD' } else { 'Disk' }
        $disks += "$(Format-Size $_.Size) $t"
    }
if (-not $disks) {
    Get-CimInstance Win32_DiskDrive | Where-Object { $_.InterfaceType -ne 'USB' } | ForEach-Object { $disks += "$(Format-Size $_.Size) Disk" }
}

# Сүлжээ: интернэт/дотоод сүлжээнд холбогдсон (gateway-тэй) адаптер
$net = Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } | Select-Object -First 1
$ip  = if ($net) { $net.IPv4Address[0].IPAddress } else { '' }
$mac = if ($net) { $net.NetAdapter.MacAddress } else { '' }

# Төрөл: явах эд ангийн (chassis) төрлөөр
$ch = @($enc.ChassisTypes)
$type = 'desktop'
if ($cs.PCSystemType -eq 2 -or ($ch | Where-Object { $_ -in 8,9,10,11,12,14,18,21,30,31,32 })) { $type = 'laptop' }
elseif ($ch | Where-Object { $_ -in 17,23,28,29 }) { $type = 'server' }
elseif ($ch -contains 13) { $type = 'all_in_one' }

$data = [ordered]@{
    name          = $env:COMPUTERNAME
    type          = $type
    brand         = $brand
    model         = $model
    serial_number = "$($bios.SerialNumber)".Trim()
    cpu           = "$((Get-CimInstance Win32_Processor | Select-Object -First 1).Name)".Trim()
    ram_gb        = $ram
    storage       = ($disks -join ' + ')
    os            = "$($os.Caption)".Trim()
    ip_address    = $ip
    mac_address   = $mac
}

# Холбогдсон төхөөрөмжүүд: гадаад дэлгэц (EDID), printer
$devices = @()
function Decode-Edid($a) { if ($a) { (($a | Where-Object { $_ -ne 0 }) | ForEach-Object { [char]$_ }) -join '' } else { '' } }
$conn = @{}; $size = @{}
Get-CimInstance -Namespace root/wmi -ClassName WmiMonitorConnectionParams | ForEach-Object { $conn[$_.InstanceName] = [int64]$_.VideoOutputTechnology }
Get-CimInstance -Namespace root/wmi -ClassName WmiMonitorBasicDisplayParams | ForEach-Object {
    $d = [math]::Sqrt([math]::Pow($_.MaxHorizontalImageSize, 2) + [math]::Pow($_.MaxVerticalImageSize, 2)) / 2.54
    if ($d -gt 0) { $size[$_.InstanceName] = [math]::Round($d) }
}
Get-CimInstance -Namespace root/wmi -ClassName WmiMonitorID | ForEach-Object {
    # Зөөврийн компьютерийн өөрийн (суурилуулсан) дэлгэцийг алгасна
    if ($conn[$_.InstanceName] -in 11, 13, 2147483648, -2147483648) { return }
    $spec = if ($size[$_.InstanceName]) { "$($size[$_.InstanceName]) инч" } else { '' }
    $devices += [ordered]@{
        category = 'monitor'; brand = (Decode-Edid $_.ManufacturerName); model = (Decode-Edid $_.UserFriendlyName)
        serial_number = (Decode-Edid $_.SerialNumberID); specs = $spec
    }
}
Get-CimInstance Win32_Printer | Where-Object {
    $_.Name -notmatch 'PDF|XPS|OneNote|Fax|AnyDesk|Send To|Snagit|Adobe|Foxit|TeamViewer|Root Print' -and
    $_.PortName -notmatch '^(PORTPROMPT|nul|FILE|SHRFAX|XPS|Microsoft)'
} | ForEach-Object {
    $pip = if ($_.PortName -match '(\d{1,3}(\.\d{1,3}){3})') { $Matches[1] } else { '' }
    $conn2 = if ($_.Network -or $pip) { 'Сүлжээний' } else { 'USB/локал' }
    $devices += [ordered]@{
        category = 'printer'; brand = ("$($_.DriverName)" -split ' ')[0]; model = "$($_.DriverName)"
        ip_address = $pip; specs = "$conn2 printer ($($_.Name))"
    }
}

Write-Host ''
foreach ($k in $data.Keys) { Write-Host ('  {0,-14} {1}' -f $k, $data[$k]) }
foreach ($d in $devices) { Write-Host ('  + {0,-12} {1} {2}' -f $d.category, $d.brand, $d.model) -ForegroundColor DarkCyan }

# Нөөц: сервер рүү холбогдож чадахгүй бол формын "Буулгах" талбарт хуулж тавина
$clip = [ordered]@{}; foreach ($k in $data.Keys) { $clip[$k] = $data[$k] }; $clip.devices = $devices
$json = $clip | ConvertTo-Json -Compress -Depth 4
Set-Clipboard -Value $json

$parts = @($data.Keys | ForEach-Object { "$_=" + [uri]::EscapeDataString("$($data[$_])") })
for ($i = 0; $i -lt $devices.Count; $i++) {
    foreach ($k in $devices[$i].Keys) { $parts += "devices[$i][$k]=" + [uri]::EscapeDataString("$($devices[$i][$k])") }
}
$query = $parts -join '&'
Start-Process ($createUrl + '?detected=1&' + $query)

Write-Host ''
Write-Host '  Бүртгэлийн хуудас хөтөч дээр нээгдлээ. Шалгаад хадгална уу.' -ForegroundColor Green
Write-Host '  Форм нээгдэхгүй бол: мэдээлэл clipboard-д хуулагдсан, формын "Буулгах" талбарт Ctrl+V хийнэ.' -ForegroundColor Yellow
Write-Host ''
PS;
}
