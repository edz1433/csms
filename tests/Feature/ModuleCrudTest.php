<?php

namespace Tests\Feature;

use App\Models\AccountTitle;
use App\Models\Delivery;
use App\Models\FundCluster;
use App\Models\InspectionAcceptanceReport;
use App\Models\Item;
use App\Models\Location;
use App\Models\Release;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class ModuleCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'access' => null,
        ]);
    }

    private function unit(): Unit
    {
        return Unit::create(['name' => 'Piece', 'abbreviation' => 'pcs']);
    }

    private function account(): AccountTitle
    {
        return AccountTitle::create(['rca_code' => '1040402100', 'name' => 'Office Supplies Inventory', 'is_active' => true]);
    }

    private function fund(): FundCluster
    {
        return FundCluster::create(['code' => '01', 'name' => 'Regular Agency Fund', 'is_active' => true]);
    }

    private function campus(): Location
    {
        return Location::create(['type' => 'campus', 'code' => '001', 'name' => 'MAIN CAMPUS', 'is_active' => true]);
    }

    private function item(array $overrides = []): Item
    {
        return Item::create(array_merge([
            'stock_number' => 'CS00001',
            'name' => 'Bond Paper',
            'unit_id' => $this->unit()->id,
            'account_title_id' => $this->account()->id,
            'on_hand_qty' => 0,
            'unit_cost' => 10,
            'is_active' => true,
        ], $overrides));
    }

    /* ============================ Auth ============================ */

    public function test_login_page_and_authentication(): void
    {
        $this->get('/login')->assertOk();

        $user = $this->admin();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_login_authenticates_matching_active_user(): void
    {
        $user = User::factory()->create([
            'name' => 'ABRIL, EDWIN Jr. T.',
            'email' => 'edzavril1@gmail.com',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'access' => null,
            'google_id' => null,
        ]);

        $googleUser = SocialiteUser::fake([
            'id' => 'google-123',
            'name' => $user->name,
            'email' => 'edzavril1@gmail.com',
            'email_verified' => true,
        ]);

        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->get(route('login.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->fresh()->google_id);
    }

    /* ==================== Reference-data CRUD ==================== */

    public function test_units_crud(): void
    {
        $this->actingAs($this->admin());

        $this->postJson(route('units.store'), ['name' => 'Ream', 'abbreviation' => 'rm'])->assertOk();
        $this->assertDatabaseHas('units', ['name' => 'Ream', 'abbreviation' => 'rm']);

        $unit = Unit::first();
        $this->putJson(route('units.update', $unit), ['name' => 'Ream Edited', 'abbreviation' => 'rm'])->assertOk();
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Ream Edited']);

        $this->deleteJson(route('units.destroy', $unit))->assertOk();
        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }

    public function test_units_store_validation_fails_without_name(): void
    {
        $this->actingAs($this->admin());
        $this->postJson(route('units.store'), ['abbreviation' => 'x'])->assertStatus(422);
    }

    public function test_suppliers_crud_and_toggle(): void
    {
        $this->actingAs($this->admin());

        $this->postJson(route('suppliers.store'), ['name' => 'Acme Trading'])->assertOk();
        $s = Supplier::first();
        $this->assertNotNull($s);

        $this->putJson(route('suppliers.update', $s), ['name' => 'Acme Inc'])->assertOk();
        $this->assertDatabaseHas('suppliers', ['id' => $s->id, 'name' => 'Acme Inc']);

        $this->patchJson(route('suppliers.toggle', $s), ['is_active' => false])->assertOk();
        $this->assertFalse($s->fresh()->is_active);

        $this->deleteJson(route('suppliers.destroy', $s))->assertOk();
        $this->assertDatabaseMissing('suppliers', ['id' => $s->id]);
    }

    public function test_locations_crud_and_toggle(): void
    {
        $this->actingAs($this->admin());

        $this->postJson(route('locations.store'), ['type' => 'office', 'code' => '001', 'name' => 'COTED'])->assertOk();
        $loc = Location::first();

        $this->putJson(route('locations.update', $loc), ['type' => 'office', 'code' => '001', 'name' => 'COTED Edited'])->assertOk();
        $this->assertDatabaseHas('locations', ['id' => $loc->id, 'name' => 'COTED Edited']);

        $this->patchJson(route('locations.toggle', $loc), ['is_active' => false])->assertOk();
        $this->assertFalse($loc->fresh()->is_active);

        $this->deleteJson(route('locations.destroy', $loc))->assertOk();
        $this->assertDatabaseMissing('locations', ['id' => $loc->id]);
    }

    public function test_fund_clusters_crud_and_toggle(): void
    {
        $this->actingAs($this->admin());

        $this->postJson(route('fund-clusters.store'), ['code' => '01', 'name' => 'Regular'])->assertOk();
        $fc = FundCluster::first();

        $this->putJson(route('fund-clusters.update', $fc), ['code' => '01', 'name' => 'Regular Edited'])->assertOk();
        $this->assertDatabaseHas('fund_clusters', ['id' => $fc->id, 'name' => 'Regular Edited']);

        $this->patchJson(route('fund-clusters.toggle', $fc), ['is_active' => false])->assertOk();
        $this->deleteJson(route('fund-clusters.destroy', $fc))->assertOk();
        $this->assertDatabaseMissing('fund_clusters', ['id' => $fc->id]);
    }

    public function test_account_titles_crud_and_toggle(): void
    {
        $this->actingAs($this->admin());

        $this->postJson(route('account-titles.store'), ['rca_code' => '1040402100', 'name' => 'Office Supplies'])->assertOk();
        $at = AccountTitle::first();

        $this->putJson(route('account-titles.update', $at), ['rca_code' => '1040402100', 'name' => 'Office Supplies Edited'])->assertOk();
        $this->assertDatabaseHas('account_titles', ['id' => $at->id, 'name' => 'Office Supplies Edited']);

        $this->patchJson(route('account-titles.toggle', $at), ['is_active' => false])->assertOk();
        $this->deleteJson(route('account-titles.destroy', $at))->assertOk();
        $this->assertDatabaseMissing('account_titles', ['id' => $at->id]);
    }

    /* ========================= Items CRUD ========================= */

    public function test_items_crud_with_autocode_and_unit_cost(): void
    {
        $this->actingAs($this->admin());
        $unit = $this->unit();

        $this->postJson(route('items.store'), [
            'name' => 'Ballpen', 'unit_id' => $unit->id, 'unit_cost' => 12.50, 'on_hand_qty' => 1.75, 'is_active' => true,
        ])->assertOk();

        $item = Item::first();
        $this->assertSame('CS00001', $item->stock_number); // auto-generated
        $this->assertEquals(12.50, (float) $item->unit_cost);
        $this->assertEquals(1.75, (float) $item->on_hand_qty);

        $this->putJson(route('items.update', $item), [
            'name' => 'Ballpen Blue', 'unit_id' => $unit->id, 'unit_cost' => 15, 'on_hand_qty' => 2.5,
        ])->assertOk();
        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Ballpen Blue']);
        $this->assertEquals(15, (float) $item->fresh()->unit_cost);
        $this->assertEquals(2.5, (float) $item->fresh()->on_hand_qty);

        $this->deleteJson(route('items.destroy', $item))->assertOk();
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_item_delete_blocked_when_it_has_history(): void
    {
        $this->actingAs($this->admin());
        $item = $this->item();
        $delivery = Delivery::create([
            'po_number' => 'PO-1', 'received_by' => $this->admin()->id, 'received_at' => now(),
        ]);
        $delivery->items()->create(['item_id' => $item->id, 'unit_id' => $item->unit_id, 'quantity' => 5, 'unit_cost' => 10]);

        $this->deleteJson(route('items.destroy', $item))->assertStatus(409);
        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }

    /* ======================= Receiving flow ======================= */

    public function test_receiving_creates_delivery_updates_stock_and_item_cost(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $item = $this->item(['on_hand_qty' => 0, 'unit_cost' => 10]);

        $res = $this->postJson(route('deliveries.store'), [
            'po_number' => 'PO-2026-01',
            'received_at' => now()->toDateString(),
            'lines' => [
                ['item_id' => $item->id, 'unit_id' => $item->unit_id, 'quantity' => 20, 'unit_cost' => 50],
            ],
        ]);
        $res->assertOk()->assertJson(['ok' => true]);

        $this->assertEquals(20, (float) $item->fresh()->on_hand_qty);       // stock added
        $this->assertEquals(50, (float) $item->fresh()->unit_cost);         // cost rolled forward
        $this->assertDatabaseHas('delivery_items', ['item_id' => $item->id, 'quantity' => 20, 'unit_cost' => 50]);
    }

    /* ================ Partial deliveries (top-up / edit) ================ */

    public function test_partial_delivery_can_be_topped_up_later_and_only_the_difference_hits_stock(): void
    {
        $this->actingAs($this->admin());
        $item = $this->item(['on_hand_qty' => 0, 'unit_cost' => 10]);

        // Day 1: 100 ordered, only 40 arrived.
        $this->postJson(route('deliveries.store'), [
            'po_number' => 'PO-2026-02',
            'received_at' => now()->subWeek()->toDateString(),
            'lines' => [[
                'item_id' => $item->id, 'unit_id' => $item->unit_id,
                'ordered_qty' => 100, 'quantity' => 40, 'unit_cost' => 50,
                'received_at' => now()->subWeek()->toDateString(),
            ]],
        ])->assertOk();

        $delivery = Delivery::with('items')->firstOrFail();
        $line = $delivery->items->first();

        $this->assertEquals(40, (float) $item->fresh()->on_hand_qty);
        $this->assertTrue($delivery->isPartial());
        $this->assertEquals(60, $delivery->outstandingQty());

        // Day 8: the balance arrives — the running total becomes 100.
        $this->putJson(route('deliveries.update', $delivery), [
            'po_number' => 'PO-2026-02',
            'received_at' => now()->subWeek()->toDateString(),
            'lines' => [[
                'id' => $line->id, 'item_id' => $item->id, 'unit_id' => $item->unit_id,
                'ordered_qty' => 100, 'quantity' => 100, 'unit_cost' => 50,
                'received_at' => now()->toDateString(),
            ]],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertEquals(100, (float) $item->fresh()->on_hand_qty); // only +60 added
        $this->assertDatabaseCount('delivery_items', 1);               // same line, topped up
        $this->assertFalse($delivery->fresh()->load('items')->isPartial());
        $this->assertEquals(now()->toDateString(), $line->fresh()->received_at->toDateString());
    }

    public function test_delivery_update_can_add_a_line_that_arrived_later(): void
    {
        $this->actingAs($this->admin());
        $first = $this->item(['on_hand_qty' => 0]);
        // Reuse the first item's unit/account title — the helper would create
        // duplicates of both.
        $second = Item::create([
            'stock_number' => 'CS00002', 'name' => 'Ballpen', 'on_hand_qty' => 0, 'unit_cost' => 20,
            'unit_id' => $first->unit_id, 'account_title_id' => $first->account_title_id, 'is_active' => true,
        ]);

        $this->postJson(route('deliveries.store'), [
            'po_number' => 'PO-2026-03',
            'received_at' => now()->toDateString(),
            'lines' => [['item_id' => $first->id, 'unit_id' => $first->unit_id, 'quantity' => 5, 'unit_cost' => 10]],
        ])->assertOk();

        $delivery = Delivery::with('items')->firstOrFail();

        $this->putJson(route('deliveries.update', $delivery), [
            'po_number' => 'PO-2026-03',
            'received_at' => now()->toDateString(),
            'lines' => [
                ['id' => $delivery->items->first()->id, 'item_id' => $first->id, 'unit_id' => $first->unit_id, 'quantity' => 5, 'unit_cost' => 10],
                ['item_id' => $second->id, 'unit_id' => $second->unit_id, 'quantity' => 7, 'unit_cost' => 20],
            ],
        ])->assertOk();

        $this->assertEquals(5, (float) $first->fresh()->on_hand_qty);   // untouched
        $this->assertEquals(7, (float) $second->fresh()->on_hand_qty);  // newly received
        $this->assertDatabaseCount('delivery_items', 2);
    }

    public function test_delivery_update_hands_stock_back_when_a_line_is_removed(): void
    {
        $this->actingAs($this->admin());
        $keep = $this->item(['on_hand_qty' => 0]);
        $drop = Item::create([
            'stock_number' => 'CS00002', 'name' => 'Ballpen', 'on_hand_qty' => 0, 'unit_cost' => 20,
            'unit_id' => $keep->unit_id, 'account_title_id' => $keep->account_title_id, 'is_active' => true,
        ]);

        $this->postJson(route('deliveries.store'), [
            'po_number' => 'PO-2026-04',
            'received_at' => now()->toDateString(),
            'lines' => [
                ['item_id' => $keep->id, 'unit_id' => $keep->unit_id, 'quantity' => 5, 'unit_cost' => 10],
                ['item_id' => $drop->id, 'unit_id' => $drop->unit_id, 'quantity' => 9, 'unit_cost' => 20],
            ],
        ])->assertOk();

        $delivery = Delivery::with('items')->firstOrFail();
        $keepLine = $delivery->items->firstWhere('item_id', $keep->id);

        $this->putJson(route('deliveries.update', $delivery), [
            'po_number' => 'PO-2026-04',
            'received_at' => now()->toDateString(),
            'lines' => [
                ['id' => $keepLine->id, 'item_id' => $keep->id, 'unit_id' => $keep->unit_id, 'quantity' => 5, 'unit_cost' => 10],
            ],
        ])->assertOk();

        $this->assertEquals(0, (float) $drop->fresh()->on_hand_qty);
        $this->assertDatabaseCount('delivery_items', 1);
    }

    public function test_delivery_update_rejects_reduction_below_released_stock(): void
    {
        $this->actingAs($this->admin());
        $item = $this->item(['on_hand_qty' => 0, 'unit_cost' => 10]);

        $this->postJson(route('deliveries.store'), [
            'po_number' => 'PO-2026-05',
            'received_at' => now()->toDateString(),
            'lines' => [['item_id' => $item->id, 'unit_id' => $item->unit_id, 'quantity' => 10, 'unit_cost' => 10]],
        ])->assertOk();

        $delivery = Delivery::with('items')->firstOrFail();
        $item->update(['on_hand_qty' => 2]); // 8 already released

        $this->putJson(route('deliveries.update', $delivery), [
            'po_number' => 'PO-2026-05',
            'received_at' => now()->toDateString(),
            'lines' => [[
                'id' => $delivery->items->first()->id, 'item_id' => $item->id,
                'unit_id' => $item->unit_id, 'quantity' => 1, 'unit_cost' => 10,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('lines');

        $this->assertEquals(2, (float) $item->fresh()->on_hand_qty);        // rolled back
        $this->assertEquals(10, (float) $delivery->items->first()->fresh()->quantity);
    }

    public function test_paid_delivery_can_no_longer_be_edited(): void
    {
        $this->actingAs($this->admin());
        $item = $this->item(['on_hand_qty' => 0]);

        $this->postJson(route('deliveries.store'), [
            'po_number' => 'PO-2026-06',
            'received_at' => now()->toDateString(),
            'lines' => [['item_id' => $item->id, 'unit_id' => $item->unit_id, 'quantity' => 4, 'unit_cost' => 10]],
        ])->assertOk();

        $delivery = Delivery::with('items')->firstOrFail();
        $delivery->update(['is_paid' => true, 'or_number' => 'OR-1', 'paid_at' => now()]);

        $this->putJson(route('deliveries.update', $delivery), [
            'po_number' => 'PO-2026-06',
            'received_at' => now()->toDateString(),
            'lines' => [[
                'id' => $delivery->items->first()->id, 'item_id' => $item->id,
                'unit_id' => $item->unit_id, 'quantity' => 40, 'unit_cost' => 10,
            ]],
        ])->assertStatus(422);

        $this->assertEquals(4, (float) $item->fresh()->on_hand_qty);
        $this->get(route('deliveries.edit', $delivery))->assertRedirect(route('deliveries.show', $delivery));
    }

    public function test_delivery_edit_form_renders_the_saved_lines(): void
    {
        $this->actingAs($this->admin());
        $item = $this->item(['on_hand_qty' => 0]);

        $this->postJson(route('deliveries.store'), [
            'po_number' => 'PO-2026-07',
            'received_at' => now()->toDateString(),
            'lines' => [[
                'item_id' => $item->id, 'unit_id' => $item->unit_id,
                'ordered_qty' => 12, 'quantity' => 3, 'unit_cost' => 10,
            ]],
        ])->assertOk();

        $delivery = Delivery::firstOrFail();

        $this->get(route('deliveries.edit', $delivery))
            ->assertOk()
            ->assertSee('PO-2026-07')
            ->assertSee('Update Delivery');

        // The partial balance is surfaced on the receipt.
        $this->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('Partial');
    }

    /* ======================= Releasing flow ======================= */

    public function test_releasing_decrements_stock_and_snapshots_cost(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $item = $this->item(['on_hand_qty' => 100, 'unit_cost' => 50]);
        $fund = $this->fund();
        $loc = $this->campus();

        $res = $this->postJson(route('releases.store'), [
            'fund_cluster_id' => $fund->id,
            'location_id' => $loc->id,
            'released_at' => now()->toDateString(),
            'lines' => [
                ['item_id' => $item->id, 'account_title_id' => $item->account_title_id, 'unit_id' => $item->unit_id, 'quantity' => 30],
            ],
        ]);
        $res->assertOk()->assertJson(['ok' => true]);

        $this->assertEquals(70, (float) $item->fresh()->on_hand_qty);       // stock deducted
        $this->assertDatabaseHas('release_items', ['item_id' => $item->id, 'quantity' => 30, 'unit_cost' => 50]);
        $this->assertDatabaseCount('releases', 1);
    }

    public function test_releasing_rejects_insufficient_stock(): void
    {
        $this->actingAs($this->admin());
        $item = $this->item(['on_hand_qty' => 5, 'unit_cost' => 50]);
        $fund = $this->fund();
        $loc = $this->campus();

        $this->postJson(route('releases.store'), [
            'fund_cluster_id' => $fund->id,
            'location_id' => $loc->id,
            'released_at' => now()->toDateString(),
            'lines' => [
                ['item_id' => $item->id, 'account_title_id' => $item->account_title_id, 'unit_id' => $item->unit_id, 'quantity' => 999],
            ],
        ])->assertStatus(422);

        $this->assertEquals(5, (float) $item->fresh()->on_hand_qty); // unchanged (rolled back)
        $this->assertDatabaseCount('releases', 0);
    }

    public function test_iar_creation_and_payment_toggle(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $delivery = Delivery::create([
            'po_number' => 'PO-9', 'received_by' => $admin->id, 'received_at' => now(),
        ]);

        $this->postJson(route('iars.store'), [
            'delivery_id' => $delivery->id,
            'iar_number' => 'IAR-2026-0001',
            'iar_date' => now()->toDateString(),
            'acceptance_status' => InspectionAcceptanceReport::STATUS_COMPLETE,
            'accepted_by' => $admin->name,
        ])->assertOk()->assertJson(['ok' => true]);

        $iar = InspectionAcceptanceReport::where('delivery_id', $delivery->id)->first();
        $this->assertNotNull($iar);

        $this->patchJson(route('iars.payment', $iar), ['or_number' => 'OR-123'])
            ->assertOk()->assertJson(['ok' => true, 'is_paid' => true]);
        $this->assertTrue($delivery->fresh()->is_paid);
        $this->assertTrue($iar->fresh()->is_paid);

        $this->patchJson(route('iars.payment', $iar->fresh()), [])
            ->assertOk()->assertJson(['is_paid' => false]);
        $this->assertFalse($delivery->fresh()->is_paid);
        $this->assertFalse($iar->fresh()->is_paid);
    }

    /* ========================= Users CRUD ========================= */

    public function test_users_crud_and_password_reset(): void
    {
        $this->actingAs($this->admin());

        $this->postJson(route('users.store'), [
            'name' => 'New Staff', 'email' => 'new@cpsu.edu.ph', 'password' => 'secret123',
            'role' => User::ROLE_SUPPLY, 'access' => ['dashboard', 'items'], 'is_active' => true,
        ])->assertOk();
        $user = User::where('email', 'new@cpsu.edu.ph')->first();
        $this->assertNotNull($user);

        $this->putJson(route('users.update', $user), [
            'name' => 'New Staff Edited', 'email' => 'new@cpsu.edu.ph', 'role' => User::ROLE_SUPPLY,
        ])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Staff Edited']);

        $this->patchJson(route('users.reset-password', $user))
            ->assertOk()->assertJsonStructure(['ok', 'temp_password']);

        $this->deleteJson(route('users.destroy', $user))->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /* ===================== Role reach (supply vs admin) ===================== */

    private function supply(): User
    {
        // No access array on purpose: Supply Staff pages come from the role.
        return User::factory()->create([
            'role' => User::ROLE_SUPPLY,
            'is_active' => true,
            'access' => null,
        ]);
    }

    public function test_supply_staff_reach_every_page_except_user_management(): void
    {
        $supply = $this->supply();
        $this->actingAs($supply);
        $this->item();

        foreach ([
            route('dashboard'),
            route('items.index'),
            route('inventory.index'),
            route('deliveries.index'),
            route('iars.index'),
            route('releases.index'),
            route('suppliers.index'),
            route('locations.index'),
            route('units.index'),
            route('fund-clusters.index'),
            route('account-titles.index'),
            route('reports.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get(route('users.index'))->assertForbidden();
        $this->assertFalse($supply->canAccess('users'));
        $this->assertTrue($supply->canAccess('reports'));

        // Accounting Staff is still steered by its per-page access array.
        $accounting = User::factory()->create([
            'role' => User::ROLE_ACCOUNTING, 'is_active' => true, 'access' => ['dashboard', 'reports'],
        ]);
        $this->assertTrue($accounting->canAccess('reports'));
        $this->assertFalse($accounting->canAccess('items'));

        $this->actingAs($accounting)->get(route('items.index'))->assertForbidden();
        $this->actingAs($accounting)->get(route('reports.index'))->assertOk();
    }

    public function test_supply_staff_cannot_touch_user_management_or_settings(): void
    {
        $this->actingAs($this->supply());
        $other = User::factory()->create(['role' => User::ROLE_ACCOUNTING, 'is_active' => true]);

        $this->postJson(route('users.store'), [
            'name' => 'Sneaky', 'email' => 'sneaky@cpsu.edu.ph', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_active' => true,
        ])->assertForbidden();

        $this->putJson(route('users.update', $other), [
            'name' => 'Renamed', 'email' => $other->email, 'role' => User::ROLE_ACCOUNTING,
        ])->assertForbidden();

        $this->deleteJson(route('users.destroy', $other))->assertForbidden();
        $this->patchJson(route('users.reset-password', $other))->assertForbidden();

        // System Settings stays administrator-only too.
        $this->get(route('settings.index'))->assertForbidden();
        $this->postJson(route('settings.update'), ['maintenance_enabled' => 1])->assertForbidden();
    }

    public function test_supply_staff_write_at_administrator_level(): void
    {
        $this->actingAs($this->supply());
        $item = $this->item();

        // Item CRUD used to be administrator-only. Stock numbers are automated.
        $this->postJson(route('items.store'), [
            'name' => 'Stapler', 'unit_id' => $item->unit_id,
            'account_title_id' => $item->account_title_id, 'unit_cost' => 120, 'is_active' => true,
        ])->assertOk();

        $created = Item::where('name', 'Stapler')->firstOrFail();

        $this->putJson(route('items.update', $created), [
            'name' => 'Stapler HD', 'unit_id' => $item->unit_id,
            'account_title_id' => $item->account_title_id, 'unit_cost' => 150, 'is_active' => true,
        ])->assertOk();
        $this->assertDatabaseHas('items', ['id' => $created->id, 'name' => 'Stapler HD']);

        $this->deleteJson(route('items.destroy', $created))->assertOk();
        $this->assertDatabaseMissing('items', ['id' => $created->id]);
    }

    public function test_supply_sidebar_hides_user_management_only(): void
    {
        $this->actingAs($this->supply());

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString(route('items.index'), $html);
        $this->assertStringContainsString(route('reports.index'), $html);
        $this->assertStringNotContainsString(route('users.index'), $html);
        $this->assertStringNotContainsString(route('settings.index'), $html);
    }

    public function test_user_management_clears_the_access_array_for_supply_accounts(): void
    {
        $this->actingAs($this->admin());

        $this->postJson(route('users.store'), [
            'name' => 'Supply Two', 'email' => 'supply2@cpsu.edu.ph', 'password' => 'secret123',
            'role' => User::ROLE_SUPPLY, 'access' => ['dashboard'], 'is_active' => true,
        ])->assertOk();

        $this->assertNull(User::where('email', 'supply2@cpsu.edu.ph')->firstOrFail()->access);
    }

    /* ==================== Reports (read + PDF) ==================== */

    public function test_report_pages_load(): void
    {
        $this->actingAs($this->admin());
        $this->item();

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('reports.index'))->assertOk();
        $this->get(route('reports.stock-card'))->assertOk();
        $this->get(route('reports.payment-status'))->assertOk();
        $this->get(route('reports.iar'))->assertOk();
        $this->get(route('reports.rsmi'))->assertOk();
        $this->get(route('reports.ledger'))->assertOk();
    }

    public function test_report_pdfs_generate(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $item = $this->item(['on_hand_qty' => 50, 'unit_cost' => 25]);

        // Ledger card PDF (Appendix 57)
        $this->get(route('reports.ledger.pdf', ['item_id' => $item->id]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // Stock card PDF (Appendix 58)
        $this->get(route('reports.stock-card.pdf', ['item_id' => $item->id]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // RSMI PDF (Appendix 64)
        $this->get(route('reports.rsmi.pdf'))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // Requisition and Issue Slip PDF (per release)
        $fund = $this->fund();
        $loc = $this->campus();
        $this->postJson(route('releases.store'), [
            'fund_cluster_id' => $fund->id, 'location_id' => $loc->id, 'released_at' => now()->toDateString(),
            'lines' => [['item_id' => $item->id, 'account_title_id' => $item->account_title_id, 'unit_id' => $item->unit_id, 'quantity' => 5]],
        ])->assertOk();
        $release = Release::first();
        $this->get(route('reports.ris.pdf', ['release_id' => $release->id]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // Releases summary PDF (generic export, streamed inline)
        $this->get(route('reports.export', ['report' => 'releases-summary', 'format' => 'pdf']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    /**
     * Alpine only binds directives inside an x-data root. The mobile menu
     * button dispatched toggle-sidebar from outside every component, so tapping
     * it did nothing on phones.
     */
    public function test_mobile_menu_button_lives_inside_an_alpine_component(): void
    {
        $html = $this->actingAs($this->admin())->get(route('dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*\bx-data\b[^>]*@click="\$dispatch\(\'toggle-sidebar\'\)"/s',
            $html,
            'The menu button must sit in an Alpine component for @click to bind.'
        );

        // Drawer and backdrop both answer the shared close event, so they cannot
        // leave a stray overlay covering the page.
        $this->assertEquals(2, substr_count($html, 'close-sidebar.window'));
    }

    /**
     * Blade does not compile directives inside a component tag's attribute, so
     * an @js() there reached the browser verbatim and left the handler as a
     * JavaScript syntax error — the button simply did nothing when clicked.
     */
    public function test_row_action_handlers_render_as_valid_javascript(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $staff = User::factory()->create(['role' => User::ROLE_SUPPLY, 'name' => "O'Brien", 'access' => ['dashboard']]);
        $unit = $this->unit();

        $userActions = view('users.partials.actions', ['user' => $staff])->render();
        $setupActions = view('setup.partials.actions', [
            'deleteUrl' => route('units.destroy', $unit),
            'label' => 'unit',
            'resource' => 'units',
            'edit' => ['units', $unit->only(['id', 'name'])],
        ])->render();

        foreach ([$userActions, $setupActions] as $html) {
            $this->assertStringNotContainsString('@js(', $html);
        }

        // The name is passed as a real JS string, quotes and all.
        $this->assertStringContainsString(route('users.reset-password', $staff), $userActions);
        $this->assertMatchesRegularExpression('/resetPassword\(\'[^\']+\', \'.*Brien.*\'\)/', $userActions);
        $this->assertStringContainsString("CPSU.deleteResource('".route('units.destroy', $unit)."', 'unit', 'units')", $setupActions);
    }

    /**
     * Unit and account title options are rendered server-side. Built with an
     * Alpine x-for they did not exist yet when x-model bound, so a new line
     * silently fell back to the first option instead of the item's own unit.
     */
    public function test_receiving_and_releasing_forms_ship_unit_options_in_the_html(): void
    {
        $this->actingAs($this->admin());
        $item = $this->item();
        Unit::create(['name' => 'Bottle', 'abbreviation' => 'btl']);

        foreach ([route('deliveries.create'), route('releases.create')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Bottle (btl)')
                ->assertSee('Piece (pcs)')
                ->assertDontSee('x-for="u in units"', false);
        }

        $this->get(route('releases.create'))
            ->assertSee($item->accountTitle->name.' — '.$item->accountTitle->rca_code);
    }
}
