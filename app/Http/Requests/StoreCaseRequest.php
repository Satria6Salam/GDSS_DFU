<?php

namespace App\Http\Requests;

use App\Enums\WoundProgress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validasi atribut kasus sesuai rentang Bab 4.2 dan aturan Bab 4.3 (T15). */
class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isLeader();
    }

    protected function prepareForValidation(): void
    {
        // Izinkan koma desimal (mis. "0,72") seperti penulisan di dokumen.
        $decimals = ['eritema', 'suhu', 'leukosit', 'abi', 'luas_luka', 'hba1c'];
        $this->merge(collect($decimals)->filter(fn ($k) => is_string($this->input($k)))
            ->mapWithKeys(fn ($k) => [$k => str_replace(',', '.', $this->input($k))])->all());

        foreach (['tanda_inflamasi', 'nyeri_istirahat', 'gangren'] as $flag) {
            $this->merge([$flag => $this->boolean($flag)]);
        }
        $this->merge(['kode_kasus' => strtoupper(trim((string) $this->input('kode_kasus')))]);
    }

    public function rules(): array
    {
        $session = $this->route('session');
        $case = $this->route('case');

        return [
            'kode_kasus' => ['required', 'regex:/^P\d{2}$/',
                Rule::unique('patient_cases', 'kode_kasus')->where('session_id', $session->id)->ignore($case?->id)],
            'usia' => ['required', 'integer', 'between:18,100'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'tipe_dm' => ['required', Rule::in(['DM tipe 1', 'DM tipe 2'])],
            'lama_dm' => ['required', 'integer', 'between:0,60'],
            'lokasi_luka' => ['required', 'string', 'max:100'],
            'tanda_inflamasi' => ['boolean'],
            'eritema' => ['required', 'numeric', 'between:0,30'],
            'suhu' => ['required', 'numeric', 'between:34,42'],
            'nadi' => ['required', 'integer', 'between:40,180'],
            'laju_napas' => ['required', 'integer', 'between:8,40'],
            'leukosit' => ['required', 'numeric', 'between:1,50'],
            'abi' => ['required', 'numeric', 'between:0.10,1.80'],
            'nadi_kaki' => ['required', Rule::in(['Teraba', 'Teraba lemah', 'Tidak teraba'])],
            'nyeri_istirahat' => ['boolean'],
            'gangren' => ['boolean'],
            'luas_luka' => ['required', 'numeric', 'between:0.1,100'],
            'kedalaman' => ['required', Rule::in(['Superfisial', 'Subkutan', 'Tendon', 'Tulang'])],
            'monofilamen' => ['required', 'integer', 'between:0,3'],
            'deformitas' => ['required', 'string', 'max:50'],
            'hba1c' => ['required', 'numeric', 'between:4,20'],
            'gds' => ['required', 'integer', 'between:40,800'],
            'egfr' => ['required', 'integer', 'between:5,150'],
            'tekanan_darah' => ['required', 'regex:/^\d{2,3}\/\d{2,3}$/'],
            'progres_luka' => ['required', Rule::enum(WoundProgress::class)],
            'alergi_obat' => ['required', 'string', 'max:200'],
            'obat_rutin' => ['required', 'string', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return [
            'kode_kasus' => 'kode kasus', 'usia' => 'usia', 'lama_dm' => 'lama DM', 'lokasi_luka' => 'lokasi luka',
            'eritema' => 'eritema', 'suhu' => 'suhu', 'nadi' => 'nadi', 'laju_napas' => 'laju napas', 'leukosit' => 'leukosit',
            'abi' => 'ABI', 'luas_luka' => 'luas luka', 'monofilamen' => 'monofilamen', 'hba1c' => 'HbA1c', 'gds' => 'GDS',
            'egfr' => 'eGFR', 'tekanan_darah' => 'tekanan darah', 'progres_luka' => 'progres luka',
            'alergi_obat' => 'alergi obat', 'obat_rutin' => 'obat rutin', 'deformitas' => 'deformitas',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'between' => ':attribute harus antara :min dan :max.',
            'integer' => ':attribute harus bilangan bulat.',
            'numeric' => ':attribute harus berupa angka.',
            'in' => 'Pilihan :attribute tidak valid.',
            'kode_kasus.regex' => 'Kode kasus harus berformat P01–P99.',
            'kode_kasus.unique' => 'Kode kasus sudah dipakai di sesi ini.',
            'tekanan_darah.regex' => 'Tekanan darah harus berformat sistolik/diastolik, mis. 120/80.',
        ];
    }
}
