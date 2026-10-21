<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Support\QrPng;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ComputerQrController extends Controller
{
    /** Компьютерийн QR кодыг PNG зургаар татуулна (QR дотор давтагдашгүй QR дугаар) */
    public function png(Computer $computer): Response
    {
        $png = QrPng::render($computer->qrContent());

        $name = 'qr-'.Str::slug($computer->inventory_number ?: 'computer-'.$computer->id).'.png';

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'no-store',
        ]);
    }
}