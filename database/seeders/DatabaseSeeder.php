<?php

namespace Database\Seeders;

use App\Models\AccountTitle;
use App\Models\FundCluster;
use App\Models\Item;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        $this->seedUnits();
        $this->seedFundClusters();
        $this->seedAccountTitles();
        $this->seedLocations();
        $this->seedSuppliers();
        $this->seedItems();
    }

    private function seedUsers(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@cpsu.edu.ph'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'access' => null,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'supply@cpsu.edu.ph'],
            [
                'name' => 'Supply Staff',
                'password' => Hash::make('password'),
                'role' => User::ROLE_SUPPLY,
                'access' => ['dashboard', 'items', 'receiving', 'releasing', 'suppliers', 'locations', 'units', 'fund_clusters', 'account_titles', 'reports'],
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'accounting@cpsu.edu.ph'],
            [
                'name' => 'Accounting Staff',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ACCOUNTING,
                'access' => ['dashboard', 'releasing', 'reports'],
                'is_active' => true,
            ]
        );
    }

    private function seedUnits(): void
    {
        foreach ([
            ['name' => 'Pieces', 'abbreviation' => 'pcs'],
            ['name' => 'Box', 'abbreviation' => 'box'],
            ['name' => 'Ream', 'abbreviation' => 'ream'],
            ['name' => 'Set', 'abbreviation' => 'set'],
            ['name' => 'Bottle', 'abbreviation' => 'btl'],
            ['name' => 'Pack', 'abbreviation' => 'pack'],
            ['name' => 'Roll', 'abbreviation' => 'roll'],
            ['name' => 'Gallon', 'abbreviation' => 'gal'],
        ] as $u) {
            Unit::updateOrCreate(['name' => $u['name']], $u);
        }
    }

    private function seedFundClusters(): void
    {
        foreach ([
            ['code' => '101', 'name' => 'Regular Agency Fund'],
            ['code' => 'MOOE-2026', 'name' => 'Maintenance & Other Operating Expenses'],
            ['code' => 'SEF', 'name' => 'Special Education Fund'],
        ] as $fc) {
            FundCluster::updateOrCreate(['code' => $fc['code']], $fc);
        }
    }

    private function seedAccountTitles(): void
    {
        foreach ([
            ['rca_code' => '5-02-03-010', 'name' => 'Office Supplies Expense'],
            ['rca_code' => '5-02-03-020', 'name' => 'Janitorial Supplies Expense'],
            ['rca_code' => '5-02-03-990', 'name' => 'Other Supplies & Materials Expense'],
            ['rca_code' => '5-02-03-030', 'name' => 'Accountable Forms Expense'],
        ] as $at) {
            AccountTitle::updateOrCreate(['rca_code' => $at['rca_code']], $at);
        }
    }

    private function seedLocations(): void
    {
        foreach ([
            ['type' => 'campus', 'code' => 'CPSU-MAIN', 'name' => 'Main Campus (Kabankalan)'],
            ['type' => 'campus', 'code' => 'CPSU-HINIG', 'name' => 'Hinigaran Campus'],
            ['type' => 'office', 'code' => 'SUPPLY-OFC', 'name' => 'Supply Office'],
            ['type' => 'office', 'code' => 'REGISTRAR', 'name' => "Registrar's Office"],
            ['type' => 'office', 'code' => 'ACCTG-OFC', 'name' => 'Accounting Office'],
        ] as $loc) {
            Location::updateOrCreate(['code' => $loc['code']], $loc);
        }
    }

    private function seedSuppliers(): void
    {
        foreach ([
            ['name' => 'Negros Office Supplies Inc.', 'contact_person' => 'Maria Santos', 'contact_number' => '0917-000-1111', 'email' => 'sales@negrosoffice.ph', 'address' => 'Bacolod City'],
            ['name' => 'Visayan Paper Trading', 'contact_person' => 'Juan Dela Cruz', 'contact_number' => '0918-222-3333', 'email' => 'orders@visayanpaper.ph', 'address' => 'Iloilo City'],
        ] as $s) {
            Supplier::updateOrCreate(['name' => $s['name']], $s);
        }
    }

    private function seedItems(): void
    {
        $pcs = Unit::where('abbreviation', 'pcs')->first();
        $ream = Unit::where('abbreviation', 'ream')->first();
        $btl = Unit::where('abbreviation', 'btl')->first();
        $office = AccountTitle::where('rca_code', '5-02-03-010')->first();
        $janitorial = AccountTitle::where('rca_code', '5-02-03-020')->first();

        foreach ([
            ['stock_number' => 'OS-0001', 'name' => 'Bond Paper A4 (sub. 20)', 'unit_id' => $ream?->id, 'account_title_id' => $office?->id, 'on_hand_qty' => 120],
            ['stock_number' => 'OS-0002', 'name' => 'Ballpen (black)', 'unit_id' => $pcs?->id, 'account_title_id' => $office?->id, 'on_hand_qty' => 500],
            ['stock_number' => 'OS-0003', 'name' => 'Stapler No. 35', 'unit_id' => $pcs?->id, 'account_title_id' => $office?->id, 'on_hand_qty' => 40],
            ['stock_number' => 'JS-0001', 'name' => 'Liquid Detergent 1L', 'unit_id' => $btl?->id, 'account_title_id' => $janitorial?->id, 'on_hand_qty' => 60],
            ['stock_number' => 'JS-0002', 'name' => 'Floor Wax', 'unit_id' => $btl?->id, 'account_title_id' => $janitorial?->id, 'on_hand_qty' => 25],
        ] as $it) {
            Item::updateOrCreate(['stock_number' => $it['stock_number']], $it + ['is_active' => true]);
        }
    }
}
