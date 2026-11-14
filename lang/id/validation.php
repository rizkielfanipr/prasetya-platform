<?php

declare(strict_types=1);

/*
 * Indonesian messages for Laravel's built-in rules. Without this file the app locale `id` showed raw keys such as
 * "validation.current_password". Rules with their own messages() keep those; this covers the rest.
 */
return [
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',
    'confirmed' => 'Ulangan :attribute tidak sama.',
    'current_password' => 'Kata sandi saat ini salah.',
    'min' => [
        'string' => ':Attribute minimal :min karakter.',
        'numeric' => ':Attribute minimal :min.',
    ],
    'max' => [
        'string' => ':Attribute maksimal :max karakter.',
        'numeric' => ':Attribute maksimal :max.',
    ],
    'unique' => ':Attribute sudah dipakai.',
    'url' => ':Attribute harus berupa URL yang valid.',
    'date' => ':Attribute harus berupa tanggal yang valid.',
    'integer' => ':Attribute harus berupa bilangan bulat.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'password' => [
        'letters' => ':Attribute harus berisi setidaknya satu huruf.',
        'mixed' => ':Attribute harus berisi huruf besar dan huruf kecil.',
        'numbers' => ':Attribute harus berisi setidaknya satu angka.',
        'symbols' => ':Attribute harus berisi setidaknya satu simbol.',
        'uncompromised' => ':Attribute ini pernah bocor di data publik. Pilih kata sandi lain.',
    ],

    'attributes' => [
        'name' => 'nama',
        'username' => 'username',
        'password' => 'kata sandi baru',
        'current_password' => 'kata sandi saat ini',
        'password_confirmation' => 'ulangan kata sandi baru',
    ],
];
