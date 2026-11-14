@php
    $isNew = ! $case->exists;
    $yesNo = ['0' => 'Tidak', '1' => 'Ya'];
    $bool = fn ($v) => $v === null ? null : ($v ? '1' : '0');
@endphp
<x-layouts.app :title="$isNew ? 'Tambah Kasus' : 'Ubah Kasus '.$case->kode_kasus" active="kasus">
    <x-slot:header>
        <x-page-header crumb="Sesi {{ $session->title }} › Kasus" :status="$session->status">
            {{ $isNew ? 'Tambah' : 'Ubah' }} <span class="acc">Kasus</span> {{ $isNew ? '' : $case->kode_kasus }}
        </x-page-header>
    </x-slot:header>

    @if ($errors->any())
        <div class="alert alert-danger rounded-4 border-0 small">Periksa kembali {{ $errors->count() }} isian yang ditandai merah.</div>
    @endif

    <form method="POST" action="{{ $isNew ? route('cases.store', $session) : route('cases.update', [$session, $case]) }}">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card-soft mb-4">
                    <h3>Identitas (DATA SIMULASI)</h3>
                    <div class="row">
                        <x-form-field class="col-sm-4" name="kode_kasus" label="Kode kasus" :value="$case->kode_kasus" hint="P01–P99" />
                        <x-form-field class="col-sm-4" name="usia" label="Usia" type="number" unit="tahun" :value="$case->usia" />
                        <x-form-field class="col-sm-4" name="jenis_kelamin" label="Jenis kelamin" :value="$case->jenis_kelamin" :options="['L' => 'L', 'P' => 'P']" />
                        <x-form-field class="col-sm-6" name="tipe_dm" label="Tipe DM" :value="$case->tipe_dm ?? 'DM tipe 2'" :options="['DM tipe 1' => 'DM tipe 1', 'DM tipe 2' => 'DM tipe 2']" />
                        <x-form-field class="col-sm-6" name="lama_dm" label="Lama DM" type="number" unit="tahun" :value="$case->lama_dm" />
                        <x-form-field class="col-12" name="lokasi_luka" label="Lokasi luka" :value="$case->lokasi_luka" />
                    </div>
                </div>

                <div class="card-soft mb-4">
                    <h3>C1 Infeksi</h3>
                    <div class="row">
                        <x-form-field class="col-sm-6" name="tanda_inflamasi" label="Tanda inflamasi (≥ 2 tanda lokal)" :value="$bool($case->tanda_inflamasi)" :options="$yesNo" />
                        <x-form-field class="col-sm-6" name="eritema" label="Eritema" unit="cm" :value="$case->eritema" hint="0–30" />
                        <x-form-field class="col-sm-4" name="suhu" label="Suhu" unit="°C" :value="$case->suhu" />
                        <x-form-field class="col-sm-4" name="nadi" label="Nadi" type="number" unit="x/mnt" :value="$case->nadi" />
                        <x-form-field class="col-sm-4" name="laju_napas" label="Laju napas" type="number" unit="x/mnt" :value="$case->laju_napas" />
                        <x-form-field class="col-sm-6" name="leukosit" label="Leukosit" unit="×10³/µL" :value="$case->leukosit" />
                    </div>
                </div>

                <div class="card-soft">
                    <h3>C2 Perfusi / iskemia</h3>
                    <div class="row">
                        <x-form-field class="col-sm-6" name="abi" label="ABI" :value="$case->abi" hint="0,10–1,80" />
                        <x-form-field class="col-sm-6" name="nadi_kaki" label="Nadi kaki" :value="$case->nadi_kaki"
                                      :options="['Teraba' => 'Teraba', 'Teraba lemah' => 'Teraba lemah', 'Tidak teraba' => 'Tidak teraba']" />
                        <x-form-field class="col-sm-6" name="nyeri_istirahat" label="Nyeri istirahat" :value="$bool($case->nyeri_istirahat)" :options="$yesNo" />
                        <x-form-field class="col-sm-6" name="gangren" label="Gangren" :value="$bool($case->gangren)" :options="$yesNo" />
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card-soft mb-4">
                    <h3>C3 Luka · C4 Neuropati</h3>
                    <div class="row">
                        <x-form-field class="col-sm-6" name="luas_luka" label="Luas luka" unit="cm²" :value="$case->luas_luka" />
                        <x-form-field class="col-sm-6" name="kedalaman" label="Kedalaman" :value="$case->kedalaman"
                                      :options="['Superfisial' => 'Superfisial', 'Subkutan' => 'Subkutan', 'Tendon' => 'Tendon', 'Tulang' => 'Tulang']" />
                        <x-form-field class="col-sm-6" name="monofilamen" label="Monofilamen terasa" type="number" unit="/ 3 titik" :value="$case->monofilamen" />
                        <x-form-field class="col-sm-6" name="deformitas" label="Deformitas" :value="$case->deformitas ?? 'Tidak ada'" />
                    </div>
                </div>

                <div class="card-soft mb-4">
                    <h3>C5 Sistemik · C6 Progres</h3>
                    <div class="row">
                        <x-form-field class="col-sm-4" name="hba1c" label="HbA1c" unit="%" :value="$case->hba1c" />
                        <x-form-field class="col-sm-4" name="gds" label="GDS" type="number" unit="mg/dL" :value="$case->gds" />
                        <x-form-field class="col-sm-4" name="egfr" label="eGFR" type="number" :value="$case->egfr" />
                        <x-form-field class="col-sm-6" name="tekanan_darah" label="Tekanan darah" unit="mmHg" :value="$case->tekanan_darah" hint="mis. 120/80" />
                        <x-form-field class="col-12" name="progres_luka" label="Progres luka" :value="$case->progres_luka"
                                      :options="collect($progressOptions)->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all()" />
                    </div>
                </div>

                <div class="card-soft">
                    <h3>Info Farmasi</h3>
                    <x-form-field name="alergi_obat" label="Alergi obat" :value="$case->alergi_obat ?? 'Tidak ada'" />
                    <x-form-field name="obat_rutin" label="Obat rutin" :value="$case->obat_rutin" />
                    <p class="small text-muted-2">Red flag otomatis (RF1, RF2, RF4, RF5) dievaluasi ulang setiap kali kasus disimpan.</p>
                    <div class="d-flex gap-2">
                        <a href="{{ $isNew ? route('cases.index', $session) : route('cases.show', [$session, $case]) }}" class="btn btn-outline-soft">Batal</a>
                        <button class="btn btn-grad"><i class="bi bi-check2 me-1"></i> Simpan Kasus</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>
