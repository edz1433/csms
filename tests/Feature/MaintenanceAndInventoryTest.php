<?php

namespace Tests\Feature;

use App\Models\AccountTitle;
use App\Models\InventoryCount;
use App\Models\InventorySession;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceAndInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true, 'access' => null]);
    }

    private function staff(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPPLY, 'is_active' => true,
            'access' => ['dashboard', 'items', 'inventory'],
        ]);
    }

    private function item(array $overrides = []): Item
    {
        $unit = Unit::firstOrCreate(['abbreviation' => 'pcs'], ['name' => 'Piece']);
        $account = AccountTitle::firstOrCreate(['rca_code' => '1040402100'], ['name' => 'Office Supplies Inventory', 'is_active' => true]);

        return Item::create(array_merge([
            'stock_number' => 'CS00001', 'name' => 'Bond Paper',
            'unit_id' => $unit->id, 'account_title_id' => $account->id,
            'on_hand_qty' => 100, 'unit_cost' => 250, 'is_active' => true,
        ], $overrides));
    }

    /** Add an inventory and cast it — the two steps every count test needs. */
    private function castInventory(User $user): InventorySession
    {
        $this->actingAs($user)->postJson(route('inventory.store'))->assertOk();
        $session = InventorySession::latest('id')->first();
        $this->actingAs($user)->patchJson(route('inventory.cast', $session))->assertOk();

        return $session->fresh();
    }

    /* ===================== Maintenance ===================== */

    public function test_administrator_can_declare_and_lift_maintenance(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('settings.index'))->assertOk()->assertSee('Maintenance Mode');

        $this->actingAs($admin)->postJson(route('settings.update'), [
            'maintenance_enabled' => 1,
            'maintenance_message' => 'Year-end physical count in progress.',
        ])->assertOk()->assertJson(['ok' => true, 'enabled' => true]);

        $this->assertTrue(Setting::bool('maintenance_enabled'));

        $this->actingAs($admin)->postJson(route('settings.update'), ['maintenance_enabled' => 0])
            ->assertOk()->assertJson(['enabled' => false]);

        $this->assertFalse(Setting::bool('maintenance_enabled'));
    }

    public function test_maintenance_blocks_staff_but_not_administrators(): void
    {
        Setting::put(['maintenance_enabled' => true, 'maintenance_message' => 'Back at 5pm.']);

        $this->actingAs($this->staff())->get(route('dashboard'))
            ->assertStatus(503)
            ->assertSee('Under maintenance')
            ->assertSee('Back at 5pm.');

        $this->actingAs($this->admin())->get(route('dashboard'))->assertOk();
    }

    public function test_staff_cannot_reach_system_settings(): void
    {
        $this->actingAs($this->staff())->get(route('settings.index'))->assertForbidden();
    }

    /* ===================== Inventory ===================== */

    public function test_an_inventory_is_added_as_a_draft_then_cast_then_closed(): void
    {
        $staff = $this->staff();

        // Adding takes no form — it drops a draft into the list.
        $this->actingAs($staff)->postJson(route('inventory.store'))->assertOk()->assertJson(['ok' => true]);

        $session = InventorySession::first();
        $this->assertTrue($session->isDraft());
        $this->assertNull(InventorySession::current());   // a draft is not counting yet

        // Asking again hands back the waiting draft rather than piling up more.
        $this->actingAs($staff)->postJson(route('inventory.store'))
            ->assertOk()->assertJson(['ok' => true, 'existing' => true, 'id' => $session->id]);
        $this->assertDatabaseCount('inventory_sessions', 1);

        $this->actingAs($staff)->patchJson(route('inventory.cast', $session))->assertOk()->assertJson(['ok' => true]);
        $this->assertTrue($session->fresh()->isActive());

        $this->actingAs($staff)->patchJson(route('inventory.close', $session))
            ->assertOk()->assertJson(['status' => 'closed']);

        $this->assertNull(InventorySession::current());
    }

    public function test_the_latest_closed_inventory_can_be_cast_again_but_older_ones_cannot(): void
    {
        $staff = $this->staff();
        $item = $this->item();

        // First inventory: counted, then closed.
        $first = $this->castInventory($staff);
        $this->actingAs($staff)->postJson(route('inventory.count'), [
            'item_id' => $item->id, 'unit_id' => $item->unit_id, 'counted_qty' => 90,
        ])->assertOk();
        $this->actingAs($staff)->patchJson(route('inventory.close', $first))->assertOk();

        // Being the latest, it can be re-opened — counts already taken survive.
        $this->actingAs($staff)->patchJson(route('inventory.cast', $first))
            ->assertOk()->assertJson(['ok' => true, 'reopened' => true]);

        $first = $first->fresh();
        $this->assertTrue($first->isActive());
        $this->assertNull($first->closed_at);
        $this->assertEquals(1, $first->progress()['counted']);

        // Close it and put a newer inventory behind it.
        $this->actingAs($staff)->patchJson(route('inventory.close', $first))->assertOk();
        $second = $this->castInventory($staff);
        $this->actingAs($staff)->patchJson(route('inventory.close', $second))->assertOk();

        // The older one is sealed for good; the newest can still be re-opened.
        $this->actingAs($staff)->patchJson(route('inventory.cast', $first))
            ->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertTrue($first->fresh()->isClosed());

        $this->actingAs($staff)->patchJson(route('inventory.cast', $second))->assertOk();
        $this->assertEquals($second->id, InventorySession::current()->id);
    }

    public function test_a_new_inventory_can_always_be_started_once_nothing_is_running(): void
    {
        $staff = $this->staff();
        $this->item();

        // Run and close three inventories back to back.
        for ($round = 1; $round <= 3; $round++) {
            $session = $this->castInventory($staff);
            $this->assertEquals('INV-'.now()->format('Y').'-'.str_pad((string) $round, 4, '0', STR_PAD_LEFT), $session->reference);

            $this->actingAs($staff)->patchJson(route('inventory.close', $session))->assertOk();
            $this->assertNull(InventorySession::current());
        }

        $this->assertDatabaseCount('inventory_sessions', 3);

        // Deleting one must not hand its reference out again.
        InventorySession::latest('id')->first()->counts()->delete();
        InventorySession::latest('id')->first()->delete();

        $this->actingAs($staff)->postJson(route('inventory.store'))->assertOk();
        $this->assertEquals('INV-'.now()->format('Y').'-0003', InventorySession::latest('id')->first()->reference);
    }

    public function test_a_draft_cannot_be_cast_while_another_inventory_runs(): void
    {
        $staff = $this->staff();

        $running = InventorySession::create([
            'reference' => 'INV-2026-0001', 'title' => 'Running', 'status' => InventorySession::STATUS_ACTIVE,
            'started_by' => $staff->id, 'started_at' => now(),
        ]);
        $draft = InventorySession::create([
            'reference' => 'INV-2026-0002', 'title' => 'Waiting', 'status' => InventorySession::STATUS_DRAFT,
            'started_by' => $staff->id, 'started_at' => now(),
        ]);

        $this->actingAs($staff)->patchJson(route('inventory.cast', $draft))->assertStatus(422);

        $this->assertTrue($draft->fresh()->isDraft());
        $this->assertEquals($running->id, InventorySession::current()->id);
    }

    public function test_scan_and_count_only_work_while_an_inventory_is_active(): void
    {
        $staff = $this->staff();
        $item = $this->item();

        // No session: the scan page renders the "no active inventory" state and
        // the count endpoint refuses the write.
        $this->actingAs($staff)->get(route('inventory.scan', $item))
            ->assertOk()->assertSee('No active inventory');

        $this->actingAs($staff)->postJson(route('inventory.count'), [
            'item_id' => $item->id, 'unit_id' => $item->unit_id, 'counted_qty' => 90,
        ])->assertStatus(409)->assertJson(['active' => false]);

        $this->assertDatabaseCount('inventory_counts', 0);

        // Cast one, then the same call is accepted.
        $this->castInventory($staff);
        $this->assertDatabaseCount('inventory_counts', 1);   // seeded, uncounted

        $this->actingAs($staff)->postJson(route('inventory.count'), [
            'item_id' => $item->id, 'unit_id' => $item->unit_id, 'counted_qty' => 90,
        ])->assertOk()->assertJson([
            'ok' => true,
            'count' => ['counted_qty' => 90, 'system_qty' => 100, 'variance' => -10],
        ]);

        $this->assertDatabaseHas('inventory_counts', ['item_id' => $item->id, 'counted_qty' => 90, 'system_qty' => 100]);
    }

    public function test_counting_can_change_the_items_unit(): void
    {
        $staff = $this->staff();
        $item = $this->item();
        $box = Unit::create(['name' => 'Box', 'abbreviation' => 'box']);

        $this->castInventory($staff);

        $this->actingAs($staff)->postJson(route('inventory.count'), [
            'item_id' => $item->id, 'unit_id' => $box->id, 'counted_qty' => 12,
        ])->assertOk();

        $this->assertEquals($box->id, $item->fresh()->unit_id);
        $this->assertDatabaseHas('inventory_counts', ['item_id' => $item->id, 'unit_id' => $box->id]);
    }

    public function test_rescanning_an_item_corrects_the_count_and_keeps_the_original_system_qty(): void
    {
        $staff = $this->staff();
        $item = $this->item();

        $this->castInventory($staff);

        $this->actingAs($staff)->postJson(route('inventory.count'), [
            'item_id' => $item->id, 'unit_id' => $item->unit_id, 'counted_qty' => 90,
        ])->assertOk();

        $item->update(['on_hand_qty' => 55]);   // stock moved after the first scan

        $this->actingAs($staff)->postJson(route('inventory.count'), [
            'item_id' => $item->id, 'unit_id' => $item->unit_id, 'counted_qty' => 95,
        ])->assertOk()->assertJson(['count' => ['counted_qty' => 95, 'system_qty' => 100]]);

        $this->assertDatabaseCount('inventory_counts', 1);
    }

    public function test_a_new_inventory_already_lists_every_item_before_it_is_cast(): void
    {
        $staff = $this->staff();
        $this->item(['on_hand_qty' => 100]);
        $this->item(['stock_number' => 'CS00002', 'name' => 'Ballpen', 'on_hand_qty' => 40]);

        $this->actingAs($staff)->postJson(route('inventory.store'))
            ->assertOk()->assertJson(['ok' => true, 'lines' => 2]);

        $session = InventorySession::first();
        $this->assertDatabaseCount('inventory_counts', 2);

        // The draft's sheet is readable straight away — no "no lines" dead end.
        $this->actingAs($staff)->get(route('inventory.show', $session))
            ->assertOk()
            ->assertSee('Bond Paper')
            ->assertSee('has not started yet')
            ->assertDontSee('cast it to build the sheet');
    }

    public function test_casting_refreshes_the_expected_quantities_on_uncounted_lines(): void
    {
        $staff = $this->staff();
        $item = $this->item(['on_hand_qty' => 100]);

        $this->actingAs($staff)->postJson(route('inventory.store'))->assertOk();
        $session = InventorySession::first();
        $this->assertEquals(100.0, (float) $session->counts()->first()->system_qty);

        // Stock moves between creating the sheet and starting the count.
        $item->update(['on_hand_qty' => 82]);
        $late = $this->item(['stock_number' => 'CS00009', 'name' => 'Late Arrival', 'on_hand_qty' => 5]);

        $this->actingAs($staff)->patchJson(route('inventory.cast', $session))
            ->assertOk()->assertJson(['seeded' => 2]);

        $this->assertEquals(82.0, (float) $session->counts()->where('item_id', $item->id)->first()->system_qty);
        $this->assertEquals(5.0, (float) $session->counts()->where('item_id', $late->id)->first()->system_qty);
    }

    public function test_casting_seeds_a_line_for_every_item_with_its_previous_quantity(): void
    {
        $staff = $this->staff();
        $paper = $this->item(['on_hand_qty' => 100]);
        $pen = $this->item(['stock_number' => 'CS00002', 'name' => 'Ballpen', 'on_hand_qty' => 40]);
        $this->item(['stock_number' => 'CS00003', 'name' => 'Retired', 'is_active' => false]);

        $session = $this->castInventory($staff);

        // Active items only, each holding the stock on record and nothing counted.
        $this->assertDatabaseCount('inventory_counts', 2);
        $this->assertDatabaseHas('inventory_counts', [
            'inventory_session_id' => $session->id, 'item_id' => $paper->id,
            'system_qty' => 100, 'counted_qty' => null, 'counted_at' => null,
        ]);
        $this->assertDatabaseHas('inventory_counts', ['item_id' => $pen->id, 'system_qty' => 40]);

        $this->assertEquals(
            ['counted' => 0, 'total' => 2, 'remaining' => 2, 'variance' => 0, 'percent' => 0],
            $session->fresh()->progress()
        );

        // The sheet lists every line with its previous quantity.
        $this->actingAs($staff)->get(route('inventory.show', $session))
            ->assertOk()
            ->assertSee('Bond Paper')
            ->assertSee('Ballpen')
            ->assertSee('Not counted')
            ->assertDontSee('Retired');
    }

    public function test_a_counted_zero_is_a_real_count_and_flips_the_line_status(): void
    {
        $staff = $this->staff();
        $item = $this->item(['on_hand_qty' => 100]);
        $session = $this->castInventory($staff);

        $line = InventoryCount::first();
        $this->assertFalse($line->isCounted());
        $this->assertNull($line->variance);              // untouched, not a shortage

        // Counting zero — everything was consumed.
        $this->actingAs($staff)->postJson(route('inventory.count'), [
            'item_id' => $item->id, 'unit_id' => $item->unit_id, 'counted_qty' => 0,
        ])->assertOk()->assertJson([
            'count' => ['counted_qty' => 0, 'variance' => -100, 'counted' => true],
            'progress' => ['counted' => 1, 'remaining' => 0, 'variance' => 1, 'percent' => 100],
        ]);

        $line = $line->fresh();
        $this->assertTrue($line->isCounted());
        $this->assertEquals(0.0, (float) $line->counted_qty);
        $this->assertEquals(-100.0, $line->variance);

        // And it exports as a counted zero, not a blank.
        $csv = $this->actingAs($staff)->get(route('inventory.export', $session))->streamedContent();
        $this->assertStringContainsString('0.00', $csv);
        $this->assertStringContainsString('Counted', $csv);
    }

    public function test_negative_quantities_are_rejected(): void
    {
        $staff = $this->staff();
        $item = $this->item();
        $this->castInventory($staff);

        $this->actingAs($staff)->postJson(route('inventory.count'), [
            'item_id' => $item->id, 'unit_id' => $item->unit_id, 'counted_qty' => -5,
        ])->assertStatus(422);

        $this->assertFalse(InventoryCount::first()->isCounted());
    }

    public function test_lookup_resolves_scan_url_and_stock_number(): void
    {
        $staff = $this->staff();
        $item = $this->item();

        $this->actingAs($staff)->getJson(route('inventory.lookup', ['code' => $item->stock_number]))
            ->assertStatus(409);   // nothing cast yet

        $this->castInventory($staff);

        $this->actingAs($staff)->getJson(route('inventory.lookup', ['code' => route('inventory.scan', $item)]))
            ->assertOk()->assertJson(['ok' => true, 'item' => ['id' => $item->id, 'system_qty' => 100]]);

        $this->actingAs($staff)->getJson(route('inventory.lookup', ['code' => $item->stock_number]))
            ->assertOk()->assertJson(['item' => ['stock_number' => 'CS00001']]);

        $this->actingAs($staff)->getJson(route('inventory.lookup', ['code' => 'NOPE-123']))
            ->assertStatus(404);
    }

    public function test_status_endpoint_reports_progress(): void
    {
        $staff = $this->staff();
        $item = $this->item();

        $this->actingAs($staff)->getJson(route('inventory.status'))->assertOk()->assertJson(['active' => false]);

        $this->castInventory($staff);
        $this->actingAs($staff)->postJson(route('inventory.count'), [
            'item_id' => $item->id, 'unit_id' => $item->unit_id, 'counted_qty' => 90,
        ])->assertOk();

        $this->actingAs($staff)->getJson(route('inventory.status'))
            ->assertOk()
            ->assertJson(['active' => true, 'progress' => ['counted' => 1, 'total' => 1, 'variance' => 1, 'percent' => 100]]);
    }

    public function test_scanner_dashboard_and_qr_labels_render(): void
    {
        $staff = $this->staff();
        $this->item();

        $this->actingAs($staff)->get(route('inventory.index'))->assertOk();
        $this->actingAs($staff)->get(route('inventory.scanner'))->assertOk()->assertSee('qr-reader', false);

        $this->actingAs($staff)->get(route('inventory.labels'))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_qr_tags_print_for_all_items_or_a_single_one(): void
    {
        $staff = $this->staff();
        $paper = $this->item(['description' => 'Substance 20, 500 sheets per ream']);
        $pen = $this->item(['stock_number' => 'CS00002', 'name' => 'Ballpen', 'on_hand_qty' => 0]);

        // Whole sheet, one card per active item.
        $this->actingAs($staff)->get(route('inventory.labels'))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // A single item's tag — reachable from its row in Items / Stocks.
        $this->actingAs($staff)->get(route('inventory.labels', ['item_id' => $paper->id]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // Narrowed by account title / stock on hand.
        $this->actingAs($staff)->get(route('inventory.labels', ['account_title_id' => $paper->account_title_id]))
            ->assertOk();
        $this->actingAs($staff)->get(route('inventory.labels', ['with_stock' => 1]))
            ->assertOk();

        // The tag markup carries the description and the scan URL behind the QR.
        $html = view('inventory-count.labels', [
            'labels' => collect([['item' => $paper->load(['unit', 'accountTitle']), 'qr' => 'data:image/png;base64,x']]),
            'single' => true,
        ])->render();

        $this->assertStringContainsString('Substance 20, 500 sheets per ream', $html);
        $this->assertStringContainsString('CS00001', $html);
        $this->assertStringContainsString('SCAN TO COUNT', $html);
        $this->assertStringContainsString('Counted qty', $html);
        $this->assertNotEquals($paper->id, $pen->id);
    }
}
