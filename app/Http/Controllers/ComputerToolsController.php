<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Support\QrPng;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Устгасан компьютерийн архив (сэргээх / бүрмөсөн устгах) болон QR шошго.
 */
class ComputerToolsController extends Controller
{
    public function trash(Request $request)
    {
        // «#12» бол дэс дугаараар, бусад үед нэр, дугаар, серийнээр
        $number = \App\Support\RowNumber::parse($request->query('q'));

        $query = Computer::onlyTrashed()
            ->with('deleter:id,name')
            ->when($number === null, fn ($q) => $q->search($request->query('q')))
            ->latest('deleted_at')->orderByDesc('id');

        return view('computers.trash', [
            'computers' => \App\Support\RowNumber::paginate($query, $request, 15, $number),
        ]);
    }

    public function restore(int $id)
    {
        $computer = Computer::onlyTrashed()->findOrFail($id);
        $computer->deleted_by = null;
        $computer->restore();

        return back()->with('success', 'Компьютер сэргээгдлээ.')->with('success_autohide', 3000);
    }

    /**
     * Бүрмөсөн устгах (сэргээх боломжгүй): зөвхөн Супер админ (route-ийн role:superadmin), өөрийн нууц үгээ
     * дахин оруулж баталгаажуулна. Админ зөвхөн архивлах (логик устгах), сэргээх эрхтэй.
     * Зөвхөн архивт (логик устгагдсан) байгаа компьютерийг устгана. Үйлдлийн түүхэнд «Бүрмөсөн устгасан» гэж бичигдэнэ.
     */
    public function forceDelete(Request $request, int $id)
    {
        $computer = Computer::onlyTrashed()->findOrFail($id);

        $data = $request->validateWithBag('forceDelete', [
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'Бүрмөсөн устгахын тулд нууц үгээ оруулна уу.',
        ]);

        if (! Hash::check($data['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Нууц үг буруу байна. Устгагдсангүй.'], 'forceDelete');
        }

        $label = $computer->activityLabel();
        $computer->forceDelete();

        return back()->with('success', $label.' бүрмөсөн устгагдлаа.')->with('success_autohide', 3000);
    }

    /** QR шошгын хуудас: QR дотор төхөөрөмжийн давтагдашгүй QR дугаар */
    public function label(Computer $computer)
    {
        $code = $computer->qrContent();

        return view('computers.label', [
            'computer' => $computer,
            'qrUrl' => $code,
            'qrSvg' => QrPng::svg($code),
        ]);
    }
}
