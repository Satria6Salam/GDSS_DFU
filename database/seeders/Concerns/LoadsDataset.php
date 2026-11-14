<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/** Membaca file JSON dataset di database/seeders/data/<versi>/ (Bab 4.9). */
trait LoadsDataset
{
    protected function dataset(string $file): array
    {
        $path = database_path('seeders/data/'.config('gdss.dataset_version').'/'.$file);

        if (! is_file($path)) {
            throw new RuntimeException("File dataset tidak ditemukan: {$path}");
        }

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /** Ubah nilai skala Saaty dari teks ("3", "1/5") menjadi angka. */
    protected function saaty(string $value): float
    {
        if (str_contains($value, '/')) {
            [$num, $den] = array_map('floatval', explode('/', $value, 2));

            return $num / $den;
        }

        return (float) $value;
    }
}
