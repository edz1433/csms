<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AccountTitle;
use App\Models\FundCluster;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fund cluster + account title scoping shared by every report.
 *
 * Two shapes are needed. Reports that query transactions (payment status,
 * RSMI) filter server-side. Reports built around a picker (RIS, stock card,
 * supply ledger) instead narrow the picker itself: the record carries the
 * fund clusters and account titles it belongs to, and the browser hides the
 * ones that no longer match. Narrowing the picker never changes the figures
 * on the generated document — a stock card still shows every movement for
 * the item, which is what keeps its running balance correct.
 */
trait ReportScope
{
    /** Dropdown options shared by all report filter bars. */
    protected function scopeLists(): array
    {
        return [
            'fundClusters' => FundCluster::orderBy('code')->get(['id', 'code', 'name']),
            'accountTitles' => AccountTitle::orderBy('name')->get(['id', 'name', 'rca_code']),
        ];
    }

    /** The scope filters on the request, as [fund cluster id, account title id]. */
    protected function scopeFilters(Request $request): array
    {
        return [
            $request->filled('fund_cluster_id') ? $request->integer('fund_cluster_id') : null,
            $request->filled('account_title_id') ? $request->integer('account_title_id') : null,
        ];
    }

    /**
     * Item query narrowed by the scope filters. Account title is the item's own;
     * fund cluster is inferred from the clusters the item has actually moved
     * under, since an item does not belong to one on its own.
     */
    protected function scopedItems(?int $fundClusterId, ?int $accountTitleId): Builder
    {
        return Item::query()
            ->when($accountTitleId, fn ($q) => $q->where('account_title_id', $accountTitleId))
            ->when($fundClusterId, function ($q) use ($fundClusterId) {
                $ids = $this->itemFundClusters()
                    ->filter(fn ($funds) => $funds->contains($fundClusterId))
                    ->keys();

                $q->whereIn('id', $ids);
            });
    }

    /**
     * Items for a picker, each tagged with the fund clusters it has moved
     * under and the account titles it has been booked against.
     */
    protected function scopedItemRecords(): Collection
    {
        $funds = $this->itemFundClusters();
        $accounts = $this->itemAccountTitles();

        return DB::table('items')->orderBy('name')->get(['id', 'name', 'stock_number', 'account_title_id'])
            ->map(fn ($item) => [
                'value' => (int) $item->id,
                'text' => ($item->stock_number ? $item->stock_number.' — ' : '').$item->name,
                'funds' => $funds->get($item->id, collect())->all(),
                'accounts' => collect([$item->account_title_id])
                    ->concat($accounts->get($item->id, collect()))
                    ->filter()->unique()->values()->map(fn ($id) => (int) $id)->all(),
            ])->values();
    }

    /** [item id => fund cluster ids] across both receipts and issues. */
    private function itemFundClusters(): Collection
    {
        $issued = DB::table('release_items as ri')
            ->join('releases as r', 'ri.release_id', '=', 'r.id')
            ->whereNotNull('r.fund_cluster_id')
            ->distinct()->get(['ri.item_id', 'r.fund_cluster_id as fund_id']);

        $received = DB::table('delivery_items as di')
            ->join('deliveries as d', 'di.delivery_id', '=', 'd.id')
            ->whereNotNull('d.fund_cluster_id')
            ->distinct()->get(['di.item_id', 'd.fund_cluster_id as fund_id']);

        return $issued->concat($received)
            ->groupBy('item_id')
            ->map(fn ($rows) => $rows->pluck('fund_id')->unique()->values()->map(fn ($id) => (int) $id));
    }

    /** [item id => account title ids] snapshotted on issue lines. */
    private function itemAccountTitles(): Collection
    {
        return DB::table('release_items')
            ->whereNotNull('account_title_id')
            ->distinct()->get(['item_id', 'account_title_id'])
            ->groupBy('item_id')
            ->map(fn ($rows) => $rows->pluck('account_title_id')->unique()->values()->map(fn ($id) => (int) $id));
    }
}
