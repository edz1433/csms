<?php

namespace App\Support;

use App\Models\Item;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Bulk item import from a CSV / tab-separated list — an uploaded file, or text
 * pasted straight out of a spreadsheet.
 *
 * Three columns are read, in this order: item name, unit cost, description.
 * A header row is matched by column name (in any order); without one the columns
 * are read positionally. Any other column in the file is ignored.
 *
 * Only the item name is required. A blank cell — and the words an export writes
 * for an empty value ("NULL", "N/A", "-") — counts as nothing: the cost becomes
 * 0.00 and the description is stored as NULL. Every imported item is measured in
 * "pcs", starts Active with no stock, and gets its code automatically (CS00001…).
 */
class ItemImport
{
    /** Guard rail: one import is a list, not a database migration. */
    public const MAX_ROWS = 5000;

    /** Matches the items.name column. Longer names spill into the description. */
    public const NAME_LIMIT = 500;

    private const DESCRIPTION_LIMIT = 2000;

    /** How a header cell is recognised, whatever the spreadsheet called it. */
    private const HEADERS = [
        'name' => ['name', 'item', 'items', 'itemname', 'itemdescription', 'particular', 'particulars', 'article', 'descriptionofarticle', 'stockdescription'],
        'unit_cost' => ['cost', 'unitcost', 'price', 'unitprice', 'amount', 'value', 'unitvalue'],
        'description' => ['description', 'desc', 'specification', 'specifications', 'specs', 'remarks', 'notes', 'note'],
    ];

    /** Cells a spreadsheet or a database export uses to mean "empty". */
    private const NULLISH = ['', 'null', '(null)', 'nil', 'na', 'n/a', 'none', '-', '--', '—'];

    /** Every imported item is measured in pieces. */
    private const DEFAULT_UNIT = 'pcs';

    private ?int $defaultUnitId = null;

    /** Lowercased item name => item id, so a repeat is spotted instantly. */
    private array $existing = [];

    /** Last CS number handed out during this import. */
    private int $sequence = 0;

    public function __construct(private bool $updateExisting = false) {}

    /* ================= parsing ================= */

    /**
     * Split raw CSV / TSV text into rows of cells. Quoted fields — including the
     * ones holding commas or line breaks — survive intact, and the delimiter is
     * whatever the file actually uses.
     */
    public static function parse(string $raw): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);           // strip UTF-8 BOM
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        $delimiter = static::detectDelimiter($raw);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $raw);
        rewind($handle);

        $rows = [];

        while (($cells = fgetcsv($handle, 0, $delimiter, '"', "\\")) !== false) {
            $cells = array_map(fn ($cell) => trim((string) $cell), $cells ?? []);

            if ($cells === [] || implode('', $cells) === '') {
                continue;                                           // blank line
            }

            $rows[] = $cells;
        }

        fclose($handle);

        return $rows;
    }

    /** The delimiter of the first non-empty line wins: comma, semicolon, tab or pipe. */
    private static function detectDelimiter(string $raw): string
    {
        $line = '';

        foreach (explode("\n", $raw) as $candidate) {
            if (trim($candidate) !== '') {
                $line = $candidate;
                break;
            }
        }

        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
            '|' => substr_count($line, '|'),
        ];

        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    /**
     * Column index per field. A header row is used when its cells are
     * recognisable column names; otherwise the fixed positional layout is
     * assumed and every row is data.
     */
    public static function mapColumns(array $firstRow): array
    {
        $map = [];

        foreach ($firstRow as $index => $cell) {
            $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower($cell));

            if ($key === '') {
                continue;
            }

            foreach (self::HEADERS as $field => $aliases) {
                if (in_array($key, $aliases, true) && ! isset($map[$field])) {
                    $map[$field] = $index;
                }
            }
        }

        // "Item Name, Unit Cost" is a header row; a lone "Bond Paper A4" is data.
        if (isset($map['name']) && count($map) >= 2) {
            return ['header' => true, 'map' => $map];
        }

        return ['header' => false, 'map' => ['name' => 0, 'unit_cost' => 1, 'description' => 2]];
    }

    /* ================= import ================= */

    /**
     * Import parsed rows. Returns a summary — how many items were created,
     * updated or skipped, plus the first few problems found — so the modal can
     * tell the user exactly what happened.
     */
    public function run(array $rows): array
    {
        $summary = [
            'rows' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0,
            'truncated' => 0, 'invalid' => 0, 'errors' => [],
        ];

        if ($rows === []) {
            return $summary;
        }

        $layout = static::mapColumns($rows[0]);
        $map = $layout['map'];
        $offset = $layout['header'] ? 1 : 0;
        $data = array_slice($rows, $offset);

        $summary['rows'] = count($data);

        if ($data === []) {
            return $summary;
        }

        $this->loadLookups();

        $create = [];
        $now = now();

        foreach ($data as $index => $cells) {
            $line = $index + $offset + 1;                           // 1-based, as a spreadsheet shows it

            if (! $layout['header']) {
                $cells = $this->coalesceUnstructuredRow($cells);
            }

            $name = $this->cell($cells, $map, 'name');

            if ($name === '') {
                $summary['invalid']++;
                $this->note($summary, "Line {$line}: no item name — skipped.");

                continue;
            }

            $description = $this->cell($cells, $map, 'description');

            if (mb_strlen($name) > self::NAME_LIMIT) {
                // Keep the full text: the overflow becomes the description
                // rather than being thrown away.
                $description = $description === '' ? $name : $name."\n".$description;
                $name = rtrim(mb_substr($name, 0, self::NAME_LIMIT));
                $summary['truncated']++;
            }

            $key = mb_strtolower($name);

            // Cost is NOT NULL in the schema, so an empty cell is 0.00; an empty
            // description really is stored as NULL, never as an empty string.
            $payload = [
                'unit_cost' => $this->number($this->cell($cells, $map, 'unit_cost')),
                'description' => $description === '' ? null : mb_substr($description, 0, self::DESCRIPTION_LIMIT),
            ];

            if (isset($this->existing[$key])) {
                // 0 marks a name queued earlier in this very file: it has no id
                // yet, so it is always a duplicate rather than an update.
                if (! $this->updateExisting || $this->existing[$key] === 0) {
                    $summary['skipped']++;
                    $this->note($summary, "Line {$line}: “{$name}” already exists — skipped.");

                    continue;
                }

                // Only the two imported fields change: an existing item keeps
                // its unit, account title and stock on hand.
                Item::whereKey($this->existing[$key])->update($payload + ['updated_at' => $now]);
                $summary['updated']++;

                continue;
            }

            $create[] = $payload + [
                'stock_number' => Item::formatStockNumber(++$this->sequence),
                'name' => $name,
                // The list carries no unit, quantity or account title: an
                // imported item is counted in pcs, starts with no stock, and is
                // Active — which is what makes it available to Receiving,
                // Releasing and the count sheet.
                'unit_id' => $this->defaultUnitId,
                'account_title_id' => null,
                'on_hand_qty' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Two identical names inside one file: the second is a duplicate of
            // the first, exactly as if it had already been in the database.
            $this->existing[$key] = 0;
        }

        foreach (array_chunk($create, 500) as $chunk) {
            DB::transaction(fn () => Item::insert($chunk));
            $summary['created'] += count($chunk);
        }

        // A mass insert fires no model events, so the sheet of a running
        // inventory is refreshed once here instead of once per row.
        if ($summary['created'] > 0 || $summary['updated'] > 0) {
            Item::syncToOpenInventorySessions();
        }

        return $summary;
    }

    /* ================= helpers ================= */

    private function loadLookups(): void
    {
        $this->defaultUnitId = Unit::where('abbreviation', self::DEFAULT_UNIT)->value('id')
            ?? Unit::firstOrCreate(['name' => 'Piece'], ['abbreviation' => self::DEFAULT_UNIT])->id;

        Item::select(['id', 'name'])->chunkById(1000, function ($items) {
            foreach ($items as $item) {
                $this->existing[mb_strtolower($item->name)] = $item->id;
            }
        });

        $this->sequence = Item::nextStockNumberSequence() - 1;
    }

    /**
     * A pasted list with no header is usually just names — and a name often
     * holds a comma ("Ballpen, black, 0.5mm"), which CSV reads as several
     * columns. When the second column is not a figure, the whole line is one
     * item name rather than a positional row.
     */
    private function coalesceUnstructuredRow(array $cells): array
    {
        $cells = array_values($cells);

        if (count($cells) < 2) {
            return $cells;
        }

        $cost = trim((string) ($cells[1] ?? ''));

        // "Item,,note" and "Item,250,note" are positional; "Ballpen, black" is a name.
        if ($this->isNullish($cost) || $this->looksNumeric($cost)) {
            return $cells;
        }

        $parts = array_filter($cells, fn ($cell) => trim((string) $cell) !== '');

        return [implode(', ', $parts)];
    }

    /** "250", "1,250.75", "₱85" — a figure rather than a word. */
    private function looksNumeric(string $value): bool
    {
        return (bool) preg_match('/^[^0-9]{0,2}[0-9][0-9.,\s]*$/u', trim($value));
    }

    /** Blank, or one of the words an export writes for an empty value. */
    private function isNullish(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), self::NULLISH, true);
    }

    private function cell(array $cells, array $map, string $field): string
    {
        $index = $map[$field] ?? null;

        if ($index === null || ! isset($cells[$index])) {
            return '';
        }

        $value = trim(preg_replace('/\s+/u', ' ', (string) $cells[$index]));

        return $this->isNullish($value) ? '' : $value;
    }

    /** "₱1,250.75" / "1,250" / "" / "NULL" → 1250.75 / 1250 / 0.00 / 0.00. */
    private function number(string $raw): float
    {
        if ($raw === '') {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9.,-]/', '', $raw);

        // A comma is a thousands separator here unless it is clearly decimal.
        if (str_contains($clean, ',') && ! str_contains($clean, '.')) {
            $clean = preg_match('/,\d{1,2}$/', $clean)
                ? str_replace(',', '.', $clean)
                : str_replace(',', '', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        return max(0, round((float) $clean, 2));
    }

    /** Only the first handful of problems are worth showing. */
    private function note(array &$summary, string $message): void
    {
        if (count($summary['errors']) < 12) {
            $summary['errors'][] = $message;
        }
    }
}
