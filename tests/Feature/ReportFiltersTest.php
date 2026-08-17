<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportsController;
use App\Models\AccountTitle;
use App\Models\Delivery;
use App\Models\FundCluster;
use App\Models\InspectionAcceptanceReport;
use App\Models\Item;
use App\Models\Location;
use App\Models\Release;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Every report can be scoped by fund cluster and account title. Transaction
 * reports (payment status, RSMI) filter their rows; picker reports (RIS,
 * stock card, ledger) narrow the picker in the browser, so here we only
 * assert the pages render with the scope data they need.
 */
class ReportFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FundCluster $fundA;

    private FundCluster $fundB;

    private AccountTitle $officeSupplies;

    private AccountTitle $drugs;

    private Item $paper;

    private Item $gauze;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN, 'is_active' => true, 'access' => null,
        ]);

        $unit = Unit::create(['name' => 'Piece', 'abbreviation' => 'pcs']);
        $this->fundA = FundCluster::create(['code' => '01', 'name' => 'Regular Agency Fund', 'is_active' => true]);
        $this->fundB = FundCluster::create(['code' => '06', 'name' => 'Trust Receipts', 'is_active' => true]);
        $this->officeSupplies = AccountTitle::create(['rca_code' => '1040402100', 'name' => 'Office Supplies Inventory', 'is_active' => true]);
        $this->drugs = AccountTitle::create(['rca_code' => '1040403000', 'name' => 'Drugs and Medicines Inventory', 'is_active' => true]);

        $this->paper = Item::create([
            'stock_number' => 'CS00001', 'name' => 'Bond Paper', 'unit_id' => $unit->id,
            'account_title_id' => $this->officeSupplies->id, 'on_hand_qty' => 100, 'unit_cost' => 250, 'is_active' => true,
        ]);
        $this->gauze = Item::create([
            'stock_number' => 'CS00002', 'name' => 'Gauze Pad', 'unit_id' => $unit->id,
            'account_title_id' => $this->drugs->id, 'on_hand_qty' => 100, 'unit_cost' => 15, 'is_active' => true,
        ]);

        // One delivery per fund cluster, each carrying its own item.
        foreach ([[$this->fundA, $this->paper, 'PO-A'], [$this->fundB, $this->gauze, 'PO-B']] as [$fund, $item, $po]) {
            $delivery = Delivery::create([
                'po_number' => $po, 'fund_cluster_id' => $fund->id,
                'received_by' => $this->admin->id, 'received_at' => now(),
            ]);
            $delivery->items()->create([
                'item_id' => $item->id, 'unit_id' => $unit->id, 'quantity' => 10, 'unit_cost' => $item->unit_cost,
            ]);
            InspectionAcceptanceReport::create([
                'delivery_id' => $delivery->id,
                'iar_number' => 'IAR-'.$po,
                'iar_date' => now()->toDateString(),
                'acceptance_status' => InspectionAcceptanceReport::STATUS_COMPLETE,
                'created_by' => $this->admin->id,
            ]);
        }

        // One release per fund cluster, mirroring the deliveries.
        $location = Location::create(['type' => 'campus', 'code' => '001', 'name' => 'MAIN CAMPUS', 'is_active' => true]);
        foreach ([[$this->fundA, $this->paper, 'RIS-A'], [$this->fundB, $this->gauze, 'RIS-B']] as [$fund, $item, $ris]) {
            $release = Release::create([
                'ris_number' => $ris, 'fund_cluster_id' => $fund->id, 'location_id' => $location->id,
                'released_by' => $this->admin->id, 'released_at' => now(),
            ]);
            $release->items()->create([
                'item_id' => $item->id, 'account_title_id' => $item->account_title_id,
                'rca_code' => $item->accountTitle->rca_code, 'unit_id' => $unit->id,
                'quantity' => 2, 'unit_cost' => $item->unit_cost,
            ]);
        }
    }

    public function test_every_report_page_renders_the_scope_filters(): void
    {
        $this->actingAs($this->admin);

        foreach (['reports.index', 'reports.stock-card', 'reports.stock-status', 'reports.account-summary',
            'reports.payment-status', 'reports.iar', 'reports.rsmi', 'reports.ledger'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('Fund Cluster')
                ->assertSee('Account Title')
                ->assertSee('Regular Agency Fund')
                ->assertSee('Office Supplies Inventory');
        }
    }

    public function test_payment_status_export_filters_by_fund_cluster(): void
    {
        $this->actingAs($this->admin);

        $csv = $this->exportCsv(['fund_cluster_id' => $this->fundA->id]);

        $this->assertStringContainsString('PO-A', $csv);
        $this->assertStringNotContainsString('PO-B', $csv);
    }

    public function test_payment_status_export_filters_by_account_title(): void
    {
        $this->actingAs($this->admin);

        $csv = $this->exportCsv(['account_title_id' => $this->drugs->id]);

        $this->assertStringContainsString('PO-B', $csv);
        $this->assertStringNotContainsString('PO-A', $csv);
    }

    public function test_rsmi_export_filters_by_fund_cluster_and_account_title(): void
    {
        $this->actingAs($this->admin);

        $byFund = $this->exportCsv(['fund_cluster_id' => $this->fundB->id], 'rsmi');
        $this->assertStringContainsString('RIS-B', $byFund);
        $this->assertStringNotContainsString('RIS-A', $byFund);

        $byAccount = $this->exportCsv(['account_title_id' => $this->officeSupplies->id], 'rsmi');
        $this->assertStringContainsString('RIS-A', $byAccount);
        $this->assertStringNotContainsString('RIS-B', $byAccount);
    }

    public function test_stock_status_lists_every_item_with_balance_and_valuation(): void
    {
        $this->actingAs($this->admin);
        Item::where('id', $this->gauze->id)->update(['on_hand_qty' => 0]);

        $this->get(route('reports.stock-status'))->assertOk()->assertSee('Stock Status');

        $this->get(route('reports.stock-status.pdf'))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // 100 x 250 = 25,000 for the paper; the zero-stock gauze still lists.
        $csv = $this->exportCsv([], 'stock-status');
        $this->assertStringContainsString('Bond Paper', $csv);
        $this->assertStringContainsString('Gauze Pad', $csv);
        $this->assertStringContainsString('25,000.00', $csv);

        // Coverage: with_stock drops the items carrying nothing.
        $withStock = $this->exportCsv(['with_stock' => 1], 'stock-status');
        $this->assertStringContainsString('Bond Paper', $withStock);
        $this->assertStringNotContainsString('Gauze Pad', $withStock);

        // Scope filters narrow it the same way as the other reports.
        $byAccount = $this->exportCsv(['account_title_id' => $this->drugs->id], 'stock-status');
        $this->assertStringContainsString('Gauze Pad', $byAccount);
        $this->assertStringNotContainsString('Bond Paper', $byAccount);

        $byFund = $this->exportCsv(['fund_cluster_id' => $this->fundA->id], 'stock-status');
        $this->assertStringContainsString('Bond Paper', $byFund);
        $this->assertStringNotContainsString('Gauze Pad', $byFund);
    }

    public function test_inventory_summary_splits_purchases_and_issues_by_month(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('reports.account-summary'))->assertOk()->assertSee('Inventory Summary');

        // Both filters are optional: no account title rolls them all together.
        $this->get(route('reports.account-summary.pdf'))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->get(route('reports.account-summary.pdf', [
            'account_title_id' => $this->officeSupplies->id,
            'year' => now()->year,
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');

        // The month maps carry this month's delivery (10 x 250) and release (2 x 250).
        $controller = new ReportsController;
        $from = Carbon::create(now()->year, 1, 1)->startOfDay();
        $to = (clone $from)->endOfYear();
        $month = (int) now()->format('n');

        $purchases = $this->callPrivate($controller, 'monthlyPurchases', [$this->officeSupplies->id, null, $from, $to]);
        $issues = $this->callPrivate($controller, 'monthlyIssues', [$this->officeSupplies->id, null, $from, $to]);

        $this->assertCount(12, $purchases);
        $this->assertEquals(2500.0, $purchases[$month]);
        $this->assertEquals(500.0, $issues[$month]);
        $this->assertEquals(0.0, $purchases[$month === 1 ? 12 : $month - 1]);

        // Fund cluster narrows it the same way as everywhere else.
        $otherFund = $this->callPrivate($controller, 'monthlyPurchases', [$this->officeSupplies->id, $this->fundB->id, $from, $to]);
        $this->assertEquals(0.0, array_sum($otherFund));

        // "All account titles" covers both items: 2,500 paper + 150 gauze.
        $allTitles = $this->callPrivate($controller, 'monthlyPurchases', [null, null, $from, $to]);
        $allIssues = $this->callPrivate($controller, 'monthlyIssues', [null, null, $from, $to]);
        $this->assertEquals(2650.0, $allTitles[$month]);
        $this->assertEquals(530.0, $allIssues[$month]);

        // "All titles" scoped to one fund cluster keeps only that cluster's side.
        $allTitlesFundB = $this->callPrivate($controller, 'monthlyPurchases', [null, $this->fundB->id, $from, $to]);
        $this->assertEquals(150.0, $allTitlesFundB[$month]);
    }

    private function callPrivate(object $object, string $method, array $args): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($object, ...$args);
    }

    public function test_stock_card_qr_tag_is_optional(): void
    {
        $this->actingAs($this->admin);

        // The toggle lives on the report page…
        $this->get(route('reports.stock-card'))
            ->assertOk()->assertSee('QR inventory tag');

        // …and only the flagged request embeds the tag.
        $plain = $this->get(route('reports.stock-card.pdf', ['item_id' => $this->paper->id]))->assertOk();
        $tagged = $this->get(route('reports.stock-card.pdf', ['item_id' => $this->paper->id, 'qr' => 1]))->assertOk();

        $this->assertGreaterThan(
            strlen($plain->getContent()),
            strlen($tagged->getContent()),
            'The QR image should make the tagged card larger.'
        );

        $html = view('inventory.pdf', [
            'item' => $this->paper->load('unit'),
            'timeline' => collect(),
            'beginning' => 0,
            'header' => null,
            'qr' => 'data:image/png;base64,x',
        ])->render();

        $this->assertStringContainsString('SCAN TO COUNT', $html);
    }

    public function test_report_pdfs_render_with_scope_filters(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('reports.rsmi.pdf', [
            'fund_cluster_id' => $this->fundA->id,
            'account_title_id' => $this->officeSupplies->id,
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->get(route('reports.ledger.pdf', ['item_id' => $this->paper->id]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    private function exportCsv(array $filters, string $report = 'payment-status'): string
    {
        $response = $this->get(route('reports.export', ['report' => $report] + $filters + [
            'format' => 'csv',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertOk();

        return $response->streamedContent();
    }
}
