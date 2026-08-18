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
        $this->seedFundClusters();
        $this->seedAccountTitles();
        $this->seedLocations();
        $this->seedSuppliers();
        $this->seedUnits();
        $this->seedItems();
    }

    private function seedUsers(): void
    {
        User::whereIn('email', [
            'admin@cpsu.edu.ph',
            'supply@cpsu.edu.ph',
            'accounting@cpsu.edu.ph',
        ])->update(['is_active' => false]);

        User::updateOrCreate(
            ['email' => 'edzavril1@gmail.com'],
            [
                'name' => 'ABRIL, EDWIN Jr. T.',
                'password' => Hash::make('adminedz@2026'),
                'role' => User::ROLE_ADMIN,
                'access' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        foreach ([
            ['name' => 'MAMAR, RAZEL C.', 'email' => 'rmamar@cpsu.edu.ph'],
            ['name' => 'LLAMAS, MA. SOCORRO T.', 'email' => 'mallamas@cpsu.edu.ph'],
            ['name' => 'TIANZON, CARLO D.', 'email' => 'ctianzon@cpsu.edu.ph'],
            ['name' => 'JAREÑO, JOHN D.', 'email' => 'jjareno@cpsu.edu.ph'],
        ] as $staff) {
            User::updateOrCreate(
                ['email' => $staff['email']],
                [
                    'name' => $staff['name'],
                    'password' => Hash::make('password'),
                    'role' => User::ROLE_SUPPLY,
                    // Supply Staff pages come from the role (everything but
                    // User Management), so no per-page array is stored.
                    'access' => null,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }

        // Accounting tags supplier payments in Receiving now (not Releasing).
        User::updateOrCreate(
            ['email' => 'cmbarcoma@cpsu.edu.ph'],
            [
                'name' => 'BARCOMA, CRISTA MAY A.',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ACCOUNTING,
                'access' => ['dashboard', 'receiving', 'iars', 'reports'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
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
        // NOTE: source list had RCA 1040409000 listed twice (Fuel and Agricultural).
        // RCA code is unique, so Agricultural uses placeholder 1040410000 — correct it
        // on the Account Titles screen once the real code is confirmed.
        foreach ([
            ['rca_code' => '1040402100', 'name' => 'Office Supplies Inventory'],
            ['rca_code' => '1040403000', 'name' => 'Accountable Forms, Plates and Stickers Inventory'],
            ['rca_code' => '1040404000', 'name' => 'Animal/Zoological Supplies Inventory'],
            ['rca_code' => '1040406000', 'name' => 'Drugs and Medicine Inventory'],
            ['rca_code' => '1040407000', 'name' => 'Medical, Dental and Laboratory Supplies Inventory'],
            ['rca_code' => '1040409000', 'name' => 'Fuel, Oil and Lubricants Inventory'],
            ['rca_code' => '1040410000', 'name' => 'Agricultural and Marine Supplies Inventory'], // placeholder code
            ['rca_code' => '1040412000', 'name' => 'Chemical and Filtering Supplies Inventory'],
            ['rca_code' => '1040499000', 'name' => 'Other Supplies and Materials Inventory'],
        ] as $at) {
            AccountTitle::updateOrCreate(['rca_code' => $at['rca_code']], $at);
        }
    }

    private function seedLocations(): void
    {
        // Campuses (numbered 001–012) and departments (001–077) reuse the same
        // numbers; codes are unique per type (see the locations migration), so
        // each list is stored with plain zero-padded codes matching the master
        // list: campus 001 = MAIN CAMPUS, office 001 = COTED, etc.
        $campuses = [
            'MAIN CAMPUS', 'CAUAYAN CAMPUS', 'CANDONI CAMPUS', 'ILOG CAMPUS',
            'SIPALAY CAMPUS', 'HINOBA-AN CAMPUS', 'HINIGARAN CAMPUS', 'MOISES PADILLA CAMPUS',
            'VICTORIAS CAMPUS', 'SAN CARLOS CAMPUS', 'MURCIA CAMPUS', 'VALLADOLID CAMPUS',
        ];

        $departments = [
            'COTED', 'CAS', 'CAF', 'COE', 'CCJE', 'CBM', 'CCS', 'ACCOUNTING',
            'BAC', 'BOR', 'BUDGET', 'CAO', 'CASHIER', 'CATTLE', 'CLINIC', 'CLONAL',
            'COA', 'DRRM', 'ELECTRICAL', 'EMS', 'ESSENTIAL OIL', 'EXTENSION SERVICES',
            'GAD', 'GRADUATE STUDIES', 'GREENTECH', 'GSO', 'GUIDANCE', 'HRMO', 'IMPDC',
            'INTERNATIONAL AFFAIRS', 'IPMO', 'KSCD', 'LIBRARY', 'MARCHINGBAND', 'MIS',
            'MOTORPOOL', 'MUSCOVADO', 'NSTP', 'OSSA', 'PEDO', 'PLANNING', 'PMO',
            'POULTRY', "PRESIDENT'S OFFICE", 'PROCUREMENT', 'QA', 'RECORDS', "REGISTRAR'S",
            'RESEARCH', 'SCHOLARSHIP', 'SOIL LAB', 'SSG', 'SUPPLY', 'TES', 'VPAF', 'VPRE',
            'VPAA', 'ASSESSMENT', 'RADYO BANDERA', 'SECURITY', 'PHYSICAL PLANT & EQUIPMENT',
            'MUSEUM', 'REVIEW & LICENSURE', 'FLP', 'JOURNAL', 'GOAT PROJECT', 'RICE PROJECT',
            'PAYROLL', 'PIGGERY PROJECT', 'ENGINEERED BAMBOO PROJECT', 'QMS',
            'TRAINING SERVICES', 'CURRICULUM PLANNING', 'CARABAO PROJECT', 'YEARBOOK',
            'FORESTRY', 'LEGAL',
        ];

        // Key on (type, code) so the code is authoritative and the name is kept
        // in sync — position 1 = 001, 2 = 002, …
        $pad = fn (int $i) => str_pad($i, 3, '0', STR_PAD_LEFT);

        foreach ($campuses as $i => $name) {
            Location::updateOrCreate(['type' => 'campus', 'code' => $pad($i + 1)], ['name' => $name]);
        }
        foreach ($departments as $i => $name) {
            Location::updateOrCreate(['type' => 'office', 'code' => $pad($i + 1)], ['name' => $name]);
        }

        // Remove legacy sample locations that aren't part of the master list,
        // but only when no release references them (FK-safe).
        $validCampus = array_map($pad, range(1, count($campuses)));
        $validOffice = array_map($pad, range(1, count($departments)));

        $strays = Location::where(fn ($q) => $q
            ->where(fn ($c) => $c->where('type', 'campus')->whereNotIn('code', $validCampus))
            ->orWhere(fn ($o) => $o->where('type', 'office')->whereNotIn('code', $validOffice)))
            ->get();

        foreach ($strays as $stray) {
            if (! \App\Models\Release::where('location_id', $stray->id)->exists()) {
                $stray->delete();
            }
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

    /** Normalize a raw (messy) unit label to a canonical [name, abbreviation]. */
    private function canonicalUnit(string $raw): array
    {
        return match (strtolower(trim($raw))) {
            'piece', 'pc/s', 'pcs' => ['Piece', 'pcs'],
            'stub' => ['Stub', 'stub'],
            'bottle', 'bot' => ['Bottle', 'btl'],
            'pack', 'pck' => ['Pack', 'pack'],
            'box', 'boxes' => ['Box', 'box'],
            'set' => ['Set', 'set'],
            'roll' => ['Roll', 'roll'],
            'jar' => ['Jar', 'jar'],
            'unit' => ['Unit', 'unit'],
            'cart' => ['Cartridge', 'cart'],
            'pad' => ['Pad', 'pad'],
            'ream' => ['Ream', 'ream'],
            'length' => ['Length', 'len'],
            'book' => ['Book', 'book'],
            'bundle' => ['Bundle', 'bdl'],
            default => ['Piece', 'pcs'],
        };
    }

    /** Seed every canonical unit explicitly so abbreviations stay consistent. */
    private function seedUnits(): void
    {
        foreach ([
            ['name' => 'Piece', 'abbreviation' => 'pcs'],
            ['name' => 'Stub', 'abbreviation' => 'stub'],
            ['name' => 'Bottle', 'abbreviation' => 'btl'],
            ['name' => 'Pack', 'abbreviation' => 'pack'],
            ['name' => 'Box', 'abbreviation' => 'box'],
            ['name' => 'Set', 'abbreviation' => 'set'],
            ['name' => 'Roll', 'abbreviation' => 'roll'],
            ['name' => 'Jar', 'abbreviation' => 'jar'],
            ['name' => 'Unit', 'abbreviation' => 'unit'],
            ['name' => 'Cartridge', 'abbreviation' => 'cart'],
            ['name' => 'Pad', 'abbreviation' => 'pad'],
            ['name' => 'Ream', 'abbreviation' => 'ream'],
            ['name' => 'Length', 'abbreviation' => 'len'],
            ['name' => 'Book', 'abbreviation' => 'book'],
            ['name' => 'Bundle', 'abbreviation' => 'bdl'],
        ] as $unit) {
            Unit::updateOrCreate(['name' => $unit['name']], ['abbreviation' => $unit['abbreviation']]);
        }
    }

    private function seedItems(): void
    {
        $units = Unit::pluck('id', 'name');
        $officeInv = AccountTitle::where('rca_code', '1040402100')->value('id');
        $formsInv = AccountTitle::where('rca_code', '1040403000')->value('id');

        $seq = 0;
        foreach ($this->itemRows() as [$name, $rawUnit]) {
            $seq++;
            [$uName] = $this->canonicalUnit($rawUnit);

            // Accountable forms map to the Accountable Forms inventory account;
            // everything else defaults to Office Supplies Inventory.
            $account = str_starts_with(strtoupper($name), 'A.F.') ? $formsInv : $officeInv;

            // Placeholder unit cost (deterministic ₱5–₱2,005) so the RSMI report
            // shows amounts out of the box — edit real costs in Item management.
            $unitCost = round(5 + (crc32($name) % 200000) / 100, 2);

            Item::updateOrCreate(
                ['stock_number' => sprintf('CS%05d', $seq)],
                [
                    'name' => $name,
                    'unit_id' => $units[$uName] ?? $units->first(),
                    'account_title_id' => $account,
                    'on_hand_qty' => 0,
                    'unit_cost' => $unitCost,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @return array<int, array{0:string,1:string,2:float}>  [name, rawUnit, legacyQty]
     */
    private function itemRows(): array
    {
        $data = <<<'ITEMS'
        3 Way Versatile 3-in-1 puncher|piece|9
        3-Hole Stainless Steel Puncher, Adjustable, black|piece|5
        A.F. #51 Accountable Forms (31681-250000)|stub|1160
        Alcohol|bottle|7
        BAllpen ball point red .05|piece|480
        Ballpen ballpoint black .05|piece|70
        Ballpen ballpoint blue .05|piece|820
        Battery 1.5V|piece|10
        Battery 9volts(Heavy duty)|pack|30
        Broom Stick|piece|2
        Carbon film (Legal)|box|1
        Certificate Holder A4, Black|pc/s|50
        Chalk, moulded, dustless, white|pc/s|15
        Charoal, yellow|pc/s|2
        Cleaner, toilet bowl|pcs|24
        Correction Tape, Film Base Type, UL 6m Min|pcs|80
        Curtain Rod with Hook and screw 198K|set|5
        Cutter knife blade|box|2
        Cutter knife, small|roll|4
        Daily time record|bot|101
        Data file box made of clip board|pc/s|8
        Dater Stamp/ Numbering|set|18
        Developer Toner TN116|box|3
        Developer Toner TNP 22k black|piece|3
        Developer Toner TNP 22k cyan|pack|4
        Developer Toner TNP 22k magenta|pc/s|5
        Developer Toner TNP 22k yellow|piece|5
        Envelope, documentary, for short size document|piece|6
        Envelope, Expanded Kraft Legal 100's|box|43
        Envelope, expanding, legal, color blue|box|19
        Envelope, mailing, white, 80gsm|box|2
        Envelope, mailing, white, with window|box|1
        Envelope, plastic short|pcs|30
        Expanded File Folder with Division|pcs|10
        Fastener, metal|box|42
        Fastener, Plastic|box|77
        File Tab Divider long|boxes|30
        Flash Drive 64gb|piece|3
        Flash drive, USB otg 64gb|pcs|2
        Folder Expanded long colored|box|50
        Folder plain white, short|box|93
        Folder Plastic, long|piece|38
        Folder sliding legal size assorted|pcs|45
        Folder, brown long|piece|100
        FOLDER, Fancy, for A4 size document|box|1
        Folder, plastic L-type long BOX|box|2
        Gestetner CPMT 25|pack|44
        Glass Cleaner|piece|9
        Glitter Cardstock Paper, short, gold, (10's)|piece|10
        Glitter Cardstock Paper, short, Silver, (10's)|piece|9
        Glue all purpose|piece|1
        Glue Stick, Small|piece|60
        Gun Tacker Staple wire 3/8|pck|19
        Gun Tacker Staple wire 5/16|piece|4
        Gun Tucker Stapler (4-14mm, 5/32"-9/16")|boxes|1
        Gun Tucker Stapler Wire 0.7x8mm|box|6
        HP laser Jet 305A;black|piece|2
        HP laser Jet 305A;cyan|bottle|1
        HP laser Jet 305A;magenta|pack|1
        Index tab, self-adhesive, colored|pack|26
        Index, PP divider|jar|49
        Ink cart, LX310 Cartridge|pcs|1
        Ink Epson 001, black|box|10
        Ink Epson 001, cyan|box|21
        Ink Epson 001, magenta|unit|9
        Ink Epson 001, yellow|box|9
        Ink Epson 008, Cyan|pcs|2
        INk Epson 008, Magenta|pcs|2
        Ink Epson 008, Yellow|Pcs|2
        Ink, (Epson T664) black|cart|32
        Ink, (Epson T664) cyan|cart|50
        INk, (Epson T664) magenta|cart|7
        INk, (Epson T664) yellow|pck|12
        Ink, Duplo Ink DA14|pack|18
        Ink, Duplo master DRG 20|cart|10
        Ink, DX 2430 black|bottle|23
        Ink, DX 2430 Ricoh|bottle|8
        Ink, epson black 003|bottle|94
        Ink, Epson cyan 003|bottle|3
        Ink, epson magenta 003|cart|9
        Ink, epson yellow 003|cart|87
        Ink, Riso F11 type|cart|19
        Ink, Riso master S-4370|bottle|8
        Ink, universal magenta|bottle|35
        Ink, universal yellow|bot|16
        Javelin 800|bottle|2
        Laminating Film 8mmx110mmx250|box|23
        Letter Envelope, White, Long, 50's|piece|2
        Letter Envelope, White, Short, 50's|piece|3
        Marker Refill INk White board|piece|4
        MArker, permanent black|bottle|44
        Marker, permanent blue|bottle|34
        Marker, permanent blue (2)|bottle|10
        Marker, permanent red|bottle|12
        Marker, refill ink black|box|1
        MARKER, whiteboard, black|piece|24
        MARKER, whiteboard, red|bottle|3
        Mini Dater stamp|bottle|3
        Mop handle assorted|piece|12
        NOTE PAD, stick-on, 1.5 x 5.1 cm(0.6 )|pack|3
        Note pad, stick-on, 50mm x 76mm (2" x 3") min|pack|45
        Note pad, stick-on, 76mm x 100mm (3" x 4") min|pck|16
        NOTE PAD, Stick-on, 76mm x 100mm (4x4)|bundle|18
        Note pad, stick-on, 76mm x 76mm (3" x 3") min|bundle|21
        Notebook, ordinary|pcs|27
        Numbering Machine|bottle|1
        Padding glue 1kg|piece|1
        Paper bond, A4 size 80gsm|boxes|90
        Paper bond, legal 80gsm|piece|718
        Paper bond, short size 80gsm|box|400
        PAPER CLAMP, Metal binder clip, 19mm|pcs|95
        PAPER CLAMP, Metal binder clip, 25mm|pcs|45
        PAPER CLAMP, Metal binder clip, 32mm|pcs|69
        PAPER CLAMP, Metal binder clip, 41mm|pcs|151
        PAPER CLAMP, Metal binder clip, 50mm|piece|104
        PAPER CLIP, Vinyl/plastic Coat, length: 32mm min|pcs|197
        PAPER CLIP, vinyl/plastic coat, length: 48mm min|piece|140
        Paper, Parchment Legal|pcs|1
        Paper, Photo glossy long, 230 gsm (10 sheets)|piece|90
        Paper, Photo long size|piece|25
        Paper, Photo Sticker Glossy A4, 50 sheets per pack, 135gsm|piece|8
        Paper, Photo Sticker transparent White Vinyl and Waterproof|pad|2
        Paper, Printable Sticker long, green matte (10 sheets)|pad|2
        Paper, Printable Sticker long, white matte (10 sheets)|pad|28
        Paper, Puncher|pad|1
        Paper, Special  A4, 200gsm|pad|33
        Paper, Special long|piece|15
        Paper, Special Long green, 80gsm|piece|10
        Paper, Special long size light green|pc/s|22
        Paper, Special long, 90gsm, 10 sheets (White)|ream|20
        Paper, Special Short, 200gsm|ream|48
        Paper, Sticker long|ream|36
        Pencil Sharpener|boxes|14
        PENCIL SHARPENER, Manual Table Type, Heavy Duty|boxes|1
        Pencil, lead, w/ eraser, wood cased, hardness, HB|boxes|0
        Pencil, lead, w/out eraser, wood cased, hardness, HB|boxes|6
        Plastic Cover 13x15 gauge 2.6|boxes|15
        Plastic Ring Binder 1-1/2" (Blue/black)|boxes|45
        Plastic Ring Binder 2 Blue 10's|boxes|6
        Plastic Ring Binder 2" (Blue/ black)|box|65
        Plastic ring Binder, 1" color blue|pcs|10
        Plastic ring Binder, 1.5|box|10
        Plastic ring binder, 2" color blue BC|pack|2
        PLASTIC RING BINDER, 225 sheet capacity 1", blue color|pack|15
        Plastic ring Binder, 3/4" color blue BC|pck|12
        Profession Sketch Paper Eraser Pens 3 and 6 Sets Blending Stump Smearing Pen Paper Rolls Pens BC|pack|5
        Push pins|pack|13
        Record book, 300 pages|pack|22
        Refill ink, (CANNON 36) colored|pack|2
        Refill ink, (CANNON 740) black|piece|14
        Refill ink, (CANNON 741) colored|pck|4
        Refill ink, (Cannon 810) black|pack|29
        Rubber band, big|pack|8
        Rubber band, small|pack|7
        Ruler, Plastic, 12-inch, transparent|pack|12
        Scissors, symmetrical, blade length: 65mm min|pack|33
        SIGN PEN, Black, Liquid/gel Ink, 0.5mm Needle, 12pcs|pack|47
        SIGN PEN, Blue, Liquid/gel Ink, 0.5mm Needle, 12pcs|piece|11
        SIGN PEN, Red, Liquid/gel Ink, 0.5mm Needle, 12pcs|pc/s|3
        Staple Wire # 35 (5000pcs per Box)|box|54
        Staple wire #10|roll|40
        Staple Wire 23/13|length|45
        Stapler binder type heavy duty desktop|length|5
        Tape dispenser|length|6
        Tape double sided|piece|80
        Tape masking (24mm)|length|0
        Tape masking (48mm)|piece|0
        Tape packaging ( 48mm)|pc/s|18
        Tape transparent ( 48mm)|piece|31
        Tape transparent (24mm)|piece|80
        Tape, magic 1/2"|pack|2
        Thumbtacks|book|54
        Toner TK-1147|bottle|18
        Toner TK-135|bottle|21
        Vellum Paper, A4, 230 Gsm, White, 10 Pcs|bottle|24
        Vellum Paper, Legal, 230 Gsm, White, 10 Pcs|bottle|20
        Vellum Paper, Short, 230 Gsm, White, 10 Pcs|pcs|11
        ITEMS;

        $rows = [];
        foreach (preg_split('/\r?\n/', trim($data)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$name, $unit, $qty] = array_pad(explode('|', $line), 3, '');
            $rows[] = [trim($name), trim($unit), (float) trim($qty)];
        }

        return $rows;
    }
}
