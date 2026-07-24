<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SukirmanSeeder extends Seeder
{
    public function run(): void
    {
        // Hapus MR dulu baru user
        MaterialRequestItem::query()->delete();
        MaterialRequest::query()->delete();
        User::whereNotIn('email', [
            'kickymaulana@gmail.com',
            'dedimaulana@gmail.com',
        ])->delete();

        $users = [
            ['nama' => 'Cayaha Dewi Br Surbakti', 'nik' => 'K040002', 'role' => 'direksi'],
            ['nama' => 'Parinton Silaen', 'nik' => '200064', 'role' => 'fm/gm'],
            ['nama' => 'Irawan', 'nik' => 'K030006', 'role' => 'manager'],
            ['nama' => 'Yohanes Paulus', 'nik' => '2D25177', 'role' => 'fm/gm'],
            ['nama' => 'Jamal Mirdad Purba', 'nik' => 'K130025', 'role' => 'supervisor'],
            ['nama' => 'Sri Lestari', 'nik' => 'D250071', 'role' => 'purchasing'],
            ['nama' => 'Saut Maruli segala', 'nik' => 'D110010', 'role' => 'manager'],
            ['nama' => 'Kicky Maulana', 'nik' => 'D260065', 'role' => 'admin'],
            ['nama' => 'Fahmi Razali Saragih', 'nik' => 'K190695', 'role' => 'gudang'],
            ['nama' => 'Ridwan', 'nik' => 'K080003', 'role' => 'direksi'],
        ];

        $createdUsers = [];
        foreach ($users as $data) {
            $email = strtolower(Str::slug($data['nama'])) . '@sukirman.test';
            $user = User::firstOrCreate(
                ['nik' => $data['nik']],
                [
                    'name' => $data['nama'],
                    'email' => $email,
                    'password' => Hash::make('sukirman'),
                    'is_approved' => true,
                    'nik' => $data['nik'],
                ]
            );
            $user->syncRoles([$data['role']]);
            $user->update(['is_approved' => true]);
            $createdUsers[] = $user;
            $this->command->info("✓ {$data['nama']} ({$data['nik']}) → {$data['role']}");
        }

        $this->command->info('');
        $this->command->info('✅ Password semua user: sukirman');

        // ====== SEEDER 50 MATERIAL REQUEST ======
        $this->command->info('');
        $this->command->info('⏳ Membuat 50 data MR...');

        $supervisors = User::role('supervisor')->get();
        $managers = User::role('manager')->get();
        $direksis = User::role('direksi')->get();
        $fmGms = User::role('fm/gm')->get();
        $gudangs = User::role('gudang')->get();

        $factories = ['KIM', 'DALU 1', 'DALU 2'];
        $types = ['Lokal', 'Import'];
        $allocations = ['Project', 'Proses'];
        $urgencies = ['Urgent', 'Normal'];

        $items = [
            ['item' => 'Baut M10 x 30 mm', 'spec' => 'Stainless steel 304', 'unit' => 'buah'],
            ['item' => 'Mur M12', 'spec' => 'Hexagon, zinc plated', 'unit' => 'buah'],
            ['item' => 'Ring Plat 16 mm', 'spec' => 'Besi hitam', 'unit' => 'buah'],
            ['item' => 'V-Belt B-56', 'spec' => 'Gates, karet', 'unit' => 'pcs'],
            ['item' => 'Bearing 6205 ZZ', 'spec' => 'SKF / NSK', 'unit' => 'pcs'],
            ['item' => 'Oli Hidrolik ISO 46', 'spec' => 'Drum 200 liter', 'unit' => 'drum'],
            ['item' => 'Grease EP-2', 'spec' => 'Lithium based, 5 kg', 'unit' => 'pail'],
            ['item' => 'Selang Hidrolik 1/4"', 'spec' => 'Tekanan 350 bar, 10 meter', 'unit' => 'rol'],
            ['item' => 'Kabel NYY 4x6 mm²', 'spec' => 'Tembaga, hitam', 'unit' => 'meter'],
            ['item' => 'MCB 3 Phase 25A', 'spec' => 'Schneider / Merlin Gerin', 'unit' => 'pcs'],
            ['item' => 'Kontaktor 32A 220V', 'spec' => 'Schneider LC1', 'unit' => 'pcs'],
            ['item' => 'Lampu LED High Bay 150W', 'spec' => 'IP65, 6500K', 'unit' => 'pcs'],
            ['item' => 'Safety Helmet', 'spec' => 'Kuning, standar SNI', 'unit' => 'pcs'],
            ['item' => 'Sepatu Safety', 'spec' => 'Chelsea boot, besi depan', 'unit' => 'pasang'],
            ['item' => 'Sarung Tangan Welding', 'spec' => 'Kulit tebal, panjang 35cm', 'unit' => 'pasang'],
            ['item' => 'Kacamata Las', 'spec' => 'Otomatis, shade 9-13', 'unit' => 'pcs'],
            ['item' => 'Masker N95', 'spec' => '3M, respirator', 'unit' => 'box'],
            ['item' => 'Earplug', 'spec' => 'Foam, reusable', 'unit' => 'pasang'],
            ['item' => 'Pipa Galvanis 2"', 'spec' => 'Sch 40, panjang 6 meter', 'unit' => 'batang'],
            ['item' => 'Sambungan Pipa 2"', 'spec' => 'Galvanis, elbow 90°', 'unit' => 'pcs'],
            ['item' => 'Katup Bola 2"', 'spec' => 'Ball valve, kuningan', 'unit' => 'pcs'],
            ['item' => 'Pressure Gauge 0-10 bar', 'spec' => 'WIKA, diameter 4"', 'unit' => 'pcs'],
            ['item' => 'Thermometer Digital', 'spec' => 'Fluke, -50 s/d 300°C', 'unit' => 'pcs'],
            ['item' => 'Timbangan Digital 100 kg', 'spec' => 'Ketelitian 10 gram', 'unit' => 'unit'],
            ['item' => 'Gerinda Tangan 4"', 'spec' => 'Makita 9553B', 'unit' => 'unit'],
            ['item' => 'Bor Listrik 13 mm', 'spec' => 'Bosch GSB 13 RE', 'unit' => 'unit'],
            ['item' => 'Mesin Las 900 Watt', 'spec' => 'MMA 200A, inverter', 'unit' => 'unit'],
            ['item' => 'Obeng Set 12 pcs', 'spec' => 'Krisbow, magnetik', 'unit' => 'set'],
            ['item' => 'Kunci Pas 10-19 mm', 'spec' => 'Krisbow, chrome vanadium', 'unit' => 'set'],
            ['item' => 'Kunci Shock 1/2" 10-24 mm', 'spec' => 'Tekiro, 12 pcs', 'unit' => 'set'],
            ['item' => 'Tang Kombinasi 8"', 'spec' => 'Krisbow', 'unit' => 'pcs'],
            ['item' => 'Multimeter Digital', 'spec' => 'Sanwa CD800a', 'unit' => 'unit'],
            ['item' => 'Tangki Air 1000 liter', 'spec' => 'Toren, plastik HDPE', 'unit' => 'unit'],
            ['item' => 'Pompa Air 1 HP', 'spec' => 'Grundfos, 220V', 'unit' => 'unit'],
            ['item' => 'Filter Udara', 'spec' => 'Element filter, 10 mikron', 'unit' => 'pcs'],
            ['item' => 'Sikat Kawat', 'spec' => 'Baja, 6 baris', 'unit' => 'pcs'],
            ['item' => 'Amplas Halus 400', 'spec' => 'Waterproof, A4', 'unit' => 'lembar'],
            ['item' => 'Cat Epoxy 5 kg', 'spec' => 'Hijau, anti karat', 'unit' => 'kaleng'],
            ['item' => 'Thinner 1 liter', 'spec' => 'ND, premium', 'unit' => 'botol'],
            ['item' => 'Kuas Cat 3"', 'spec' => 'Bulu kambing', 'unit' => 'pcs'],
            ['item' => 'Rol Cat 9"', 'spec' => 'Microfiber', 'unit' => 'pcs'],
            ['item' => 'Lakban 2"', 'spec' => 'Coklat, 50 meter', 'unit' => 'rol'],
            ['item' => 'Kardus 60x40x30', 'spec' => 'Single wall, 3 ply', 'unit' => 'pcs'],
            ['item' => 'Tali Rafia 500 gram', 'spec' => 'Plastik, warna-warni', 'unit' => 'rol'],
            ['item' => 'Kunci Gembok 50 mm', 'spec' => 'Besi, anti karat', 'unit' => 'pcs'],
            ['item' => 'Lemper', 'spec' => 'Stick, 10 pcs per pack', 'unit' => 'pack'],
            ['item' => 'Sekop', 'spec' => 'Baja, gagang kayu 1 m', 'unit' => 'pcs'],
            ['item' => 'Cangkul', 'spec' => 'Baja, gagang kayu', 'unit' => 'pcs'],
            ['item' => 'Sapu Ijuk', 'spec' => 'Ijuk, gagang kayu', 'unit' => 'pcs'],
            ['item' => 'Ember Plastik 20 liter', 'spec' => 'HDPE, biru', 'unit' => 'pcs'],
        ];

        $statuses = ['Pending Manager', 'Pending FM/GM', 'Pending Direksi', 'Verifikasi Gudang', 'Fully Approved', 'Rejected', 'Revision'];

        for ($i = 0; $i < 50; $i++) {
            $supervisor = $supervisors->random();
            $status = $statuses[array_rand($statuses)];
            $factory = $factories[array_rand($factories)];
            $type = $types[array_rand($types)];
            $allocation = $allocations[array_rand($allocations)];
            $urgency = $urgencies[array_rand($urgencies)];

            $mr = MaterialRequest::create([
                'mr_number' => 'MR-SEED-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'user_id' => $supervisor->id,
                'manager_id' => $managers->random()->id,
                'status_workflow' => $status,
                'type' => $type,
                'factory' => $factory,
                'allocation' => $allocation,
                'status_pembelian' => $urgency,
            ]);

            // 1-4 item per MR
            $numItems = rand(1, 4);
            $usedKeys = [];
            for ($j = 0; $j < $numItems; $j++) {
                $key = array_rand($items);
                while (in_array($key, $usedKeys)) $key = array_rand($items);
                $usedKeys[] = $key;
                $item = $items[$key];

                MaterialRequestItem::create([
                    'material_request_id' => $mr->id,
                    'item_name' => $item['item'],
                    'specification' => $item['spec'],
                    'qty' => rand(2, 50),
                    'unit' => $item['unit'],
                    'purpose' => collect(['Perbaikan mesin produksi', 'Penggantian spare part', 'Kebutuhan operasional harian', 'Stock gudang', 'Perawatan rutin', 'Proyek pabrik'])->random(),
                ]);
            }
        }

        $this->command->info('✅ 50 data MR berhasil dibuat');
    }
}
