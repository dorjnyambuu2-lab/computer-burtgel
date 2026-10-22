<?php

namespace App\Http\Requests;

use App\Enums\ComputerStatus;
use App\Enums\ComputerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComputerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('computer')?->id;

        // Шинээр бүртгэхэд бүртгэлийн дугаар, нэрийг хоосон орхиж болно: Computer model автоматаар үүсгэнэ.
        // Засахад хоосон болгохыг зөвшөөрөхгүй.
        $required = $id ? 'required' : 'nullable';

        return [
            'inventory_number' => [$required, 'string', 'max:100', Rule::unique('computers', 'inventory_number')->ignore($id)],
            // «Бусад» ангиллаар ганц төхөөрөмж бүртгэхэд юу болохыг нэрээр нь заавал бичнэ (Проектор, Веб камер ...)
            'name' => [$id ? 'required' : Rule::requiredIf(fn () => $this->input('categories') === ['other'] || $this->input('category') === 'other'), 'nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ComputerType::class)],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('computers', 'serial_number')->ignore($id)],
            'cpu' => ['nullable', 'string', 'max:255'],
            'ram_gb' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'storage' => ['nullable', 'string', 'max:255'],
            'os' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'mac_address' => ['nullable', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/',
                // MAC нь төхөөрөмж бүрт өөр — өөр компьютерт бүртгэлтэй бол хадгалахгүй (AA-BB.. / aa:bb.. бичлэгийг ижил гэж үзнэ)
                function (string $attribute, mixed $value, \Closure $fail) use ($id) {
                    $other = \App\Models\Computer::withoutGlobalScopes()->whereNull('deleted_at')
                        ->whereRaw("upper(replace(mac_address, '-', ':')) = ?", [strtoupper(str_replace('-', ':', (string) $value))])
                        ->when($id, fn ($q) => $q->whereKeyNot($id))
                        ->first(['id', 'sequence_number', 'name']);
                    if ($other) {
                        $fail("Энэ MAC хаяг «#{$other->sequence_number} {$other->name}» төхөөрөмжид бүртгэлтэй байна.");
                    }
                }],
            'specs' => ['nullable', 'string', 'max:500'],
            // Салбар: Супер админ сонгоно; Админд үргэлж өөрийн салбар (ComputerController::payload хасна)
            'branch_id' => [$this->user()?->isSuperadmin() ? 'required' : 'nullable', 'integer', 'exists:branches,id'],
            'department' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'holder_surname' => ['nullable', 'string', 'max:255'],
            'holder_name' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ComputerStatus::class)],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'warranty_until' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'inventory_number' => 'Бүртгэлийн дугаар',
            'name' => 'Компьютерийн нэр',
            'type' => 'Төрөл',
            'brand' => 'Үйлдвэрлэгч',
            'model' => 'Загвар',
            'serial_number' => 'Серийн дугаар',
            'cpu' => 'Процессор',
            'ram_gb' => 'RAM',
            'storage' => 'Хадгалах төхөөрөмж',
            'os' => 'Үйлдлийн систем',
            'ip_address' => 'IP хаяг',
            'mac_address' => 'MAC хаяг',
            'specs' => 'Үзүүлэлт',
            'branch_id' => 'Салбар',
            'department' => 'Алба',
            'unit' => 'Нэгж',
            'position' => 'Албан тушаал',
            'location' => 'Байршил',
            'holder_surname' => 'Овог',
            'holder_name' => 'Нэр',
            'assigned_to' => 'Хариуцагч',
            'status' => 'Төлөв',
            'purchase_date' => 'Худалдан авсан огноо',
            'purchase_price' => 'Үнэ',
            'warranty_until' => 'Баталгаат хугацаа',
            'notes' => 'Тэмдэглэл',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute талбарыг заавал бөглөнө.',
            'name.required' => 'Төхөөрөмжийн нэрийг бичнэ үү (жишээ: Проектор, Веб камер).',
            'unique' => 'Энэ :attribute аль хэдийн бүртгэгдсэн байна.',
            'max' => ':attribute хэт урт байна.',
            'integer' => ':attribute бүхэл тоо байх ёстой.',
            'numeric' => ':attribute-ийг тоогоор оруулна уу (жишээ: 2,500,000).',
            'min' => ':attribute буруу утгатай байна.',
            'ip' => ':attribute буруу форматтай байна.',
            'date' => ':attribute буруу огноо байна.',
            'enum' => ':attribute буруу утгатай байна.',
            'mac_address.regex' => 'MAC хаяг AA:BB:CC:DD:EE:FF хэлбэртэй байх ёстой.',
            'warranty_until.after_or_equal' => 'Баталгаат хугацаа худалдан авсан огнооноос өмнө байж болохгүй.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Хоосон мөрийг null болгож, MAC хаягийг том үсэгт шилжүүлнэ
        $data = collect($this->all())->map(fn ($v) => is_string($v) && trim($v) === '' ? null : (is_string($v) ? trim($v) : $v));

        // Баталгаа дуусах огноо хоосон бол худалдан авсан огноо + баталгааны хугацаагаар бодно (JS ажиллаагүй үед)
        if (! $data->get('warranty_until') && ($until = \App\Support\Warranty::until($data->get('purchase_date'), $data->get('warranty_months')))) {
            $data->put('warranty_until', $until);
        }

        // Үнийг «1,250,000₮» гэж бичсэн ч тоо болгоно
        if ($data->has('purchase_price')) {
            $data->put('purchase_price', \App\Support\Money::parse($data->get('purchase_price')));
        }

        if ($data->get('mac_address')) {
            $data->put('mac_address', strtoupper(str_replace('-', ':', $data->get('mac_address'))));
        }

        // Шинээр бүртгэхэд бүртгэлийн дугаарыг гараар оруулахгүй: үргэлж автоматаар (PC-20261019-001) үүснэ
        if (! $this->route('computer')) {
            $data->put('inventory_number', null);
        }

        $this->replace($data->all());
    }
}
