<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\ComputerTransfer;
use Illuminate\Http\Request;

class MyComputerController extends Controller
{
    /** Хэрэглэгчид хариуцуулсан компьютерууд (хэрэглэгчийн ID-аар, нэр давхцахаас хамаарахгүй) */
    public static function ownedBy($user)
    {
        return Computer::query()->where('assigned_user_id', $user->id);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $all = self::ownedBy($user)->orderBy('name')->get();

        // Ангиллаар нь бүлэглэнэ ("Ангилаагүй" нь 'none' түлхүүртэй)
        $keyOf = fn ($c) => $c->category?->value ?? 'none';
        $counts = $all->countBy($keyOf);

        // ?category=monitor гэх мэтээр шүүнэ (танигдаагүй утга бол бүгдийг харуулна)
        $selected = $request->query('category');
        if (! is_string($selected) || ! $counts->has($selected)) {
            $selected = null;
        }

        $computers = $selected ? $all->filter(fn ($c) => $keyOf($c) === $selected)->values() : $all;
        $grouped = $computers->groupBy($keyOf);
        // Өмнө илгээсэн, шийдвэрлэгдээгүй хүсэлтүүд [computer_id => transfer] (шилжүүлэх эрх одоо зөвхөн Супер админ, Админд)
        $openTransfers = ComputerTransfer::where('from_user_id', $user->id)->open()->get()->keyBy('computer_id');
        $pendingIds = $openTransfers->keys()->all();

        return view('my.computers', compact('computers', 'grouped', 'counts', 'selected', 'pendingIds', 'openTransfers'));
    }

    /** Ажилтан зөвхөн ӨӨРТ оноогдсон компьютерийн үзүүлэлтийг харна (бусдынх 404 өгнө) */
    public function show(Request $request, int $id)
    {
        $computer = self::ownedBy($request->user())->where('id', $id)->firstOrFail();

        return view('my.show', compact('computer'));
    }

    public function acknowledge(Request $request, int $id)
    {
        $computer = self::ownedBy($request->user())->where('id', $id)->firstOrFail();

        if ($computer->acknowledged_at === null) {
            $computer->acknowledged_at = now();
            $computer->save();
        }
        // Акт баталгаажуулж буй компьютерийн IP, MAC-г автоматаар бүртгэнэ
        rescue(fn () => \App\Support\ClientNetwork::syncForEmployee($request->user(), $request), null, report: true);

        return back()->with('success', 'Хүлээн авснаа баталгаажууллаа.');
    }
}