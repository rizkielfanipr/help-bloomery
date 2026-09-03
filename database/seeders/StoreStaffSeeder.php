<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StoreStaffSeeder extends Seeder
{
    public function run(): void
    {
        $stores = [
            ['name' => 'Store Tamansiswa',       'username' => 'BLOTMS', 'password' => 'Tamansiswa123#'],
            ['name' => 'Store Kaliurang',         'username' => 'BLOJKL', 'password' => 'Kaliurang123#'],
            ['name' => 'Store Balcos',            'username' => 'BLOBLS', 'password' => 'Balcos123#'],
            ['name' => 'Store Joy-Mart Tugu',     'username' => 'BLOJYT', 'password' => 'Tugu123#'],
            ['name' => 'Store Eggish',            'username' => 'BLOEGG', 'password' => 'Eggish123#'],
            ['name' => 'Store Joy-Mart Keprabon', 'username' => 'BLOJYK', 'password' => 'Keprabon123#'],
            ['name' => 'Store Pabelan',           'username' => 'BLOPBL', 'password' => 'Pabelan123#'],
            ['name' => 'Store Kotalama',          'username' => 'BLOKLM', 'password' => 'Kotalama123#'],
            ['name' => 'Store Tembalang',         'username' => 'BLOTMB', 'password' => 'Tembalang123#'],
            ['name' => 'Store Istana Buah',       'username' => 'BLOTNB', 'password' => 'Istana123#'],
            ['name' => 'Store Surabaya',          'username' => 'BLOSBY', 'password' => 'Surabaya123#'],
            ['name' => 'Store Pesanggrahan',      'username' => 'BLOPSG', 'password' => 'Pesanggrahan123#'],
            ['name' => 'Store Haji Nawi',         'username' => 'BLOHJW', 'password' => 'Nawi123#'],
            ['name' => 'Store Blok M',            'username' => 'BLOBLM', 'password' => 'Blokm123#'],
            ['name' => 'Store Gading Serpong',    'username' => 'BLOGSG', 'password' => 'Serpong123#'],
        ];

        foreach ($stores as $store) {
            $user = User::firstOrCreate(
                ['username' => $store['username']],
                [
                    'name' => $store['name'],
                    'email' => strtolower($store['username']).'@bloomery.internal',
                    'password' => Hash::make($store['password']),
                ]
            );

            $user->syncRoles(['STORE_STAFF']);
        }
    }
}
