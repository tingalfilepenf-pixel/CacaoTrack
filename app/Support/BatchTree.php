<?php

namespace App\Support;

use App\Models\Batch;

/**
 * Single ordered parent → children view of a batch collection (RULE 6).
 *
 * The Phase 7B regraft flow creates linked sub-batches (`parent_batch_id`), and
 * a sub-batch can itself be regrafted into another sub-batch, so the family can
 * be arbitrarily deep. Every nested view (Batches, Inventory, Stock Ledger and
 * the historical reports) reads its ordering and depth from here rather than
 * re-implementing the traversal.
 *
 * Rules:
 *  - roots are ordered by `grafted_date` (nulls first) then `batch_code`;
 *  - children are ordered by `batch_code`;
 *  - a batch whose parent is present in the collection is nested under it;
 *  - a batch whose parent is **not** in the collection (filtered out) is shown
 *    at depth 0 and flagged `parent_missing` so the view can render a
 *    "(parent: CODE)" marker — rows are never dropped;
 *  - missing parent codes are resolved with a single query (never N+1).
 */
final class BatchTree
{
    /**
     * Flatten a batch collection into an ordered depth-tagged list.
     *
     * @param  iterable<int,Batch>  $batches
     * @return list<array{batch:Batch,depth:int,parent_code:?string,parent_missing:bool,ancestors:list<int>}>
     */
    public static function flatten(iterable $batches): array
    {
        $byId = [];

        foreach ($batches as $batch) {
            $byId[(int) $batch->batch_id] = $batch;
        }

        if ($byId === []) {
            return [];
        }

        $children = [];
        $missingParentIds = [];

        foreach ($byId as $id => $batch) {
            $parentId = self::parentId($batch);

            if ($parentId === null) {
                continue;
            }

            $children[$parentId][] = $id;

            if (! isset($byId[$parentId]) && ! $batch->relationLoaded('parent')) {
                $missingParentIds[$parentId] = true;
            }
        }

        // Resolve every filtered-out parent's code in one query.
        $missingParentCodes = [];

        if ($missingParentIds !== []) {
            $missingParentCodes = Batch::query()
                ->whereIn('batch_id', array_keys($missingParentIds))
                ->pluck('batch_code', 'batch_id')
                ->mapWithKeys(fn ($code, $id) => [(int) $id => (string) $code])
                ->all();
        }

        // Roots: no parent, or a parent that is not part of this collection.
        $roots = [];

        foreach ($byId as $id => $batch) {
            $parentId = self::parentId($batch);

            if ($parentId === null || ! isset($byId[$parentId])) {
                $roots[] = $batch;
            }
        }

        usort($roots, self::compareRoots(...));

        $nodes = [];
        $seen = [];

        $append = function (Batch $batch, int $depth, array $ancestors) use (&$append, &$nodes, &$seen, $byId, $children, $missingParentCodes): void {
            $id = (int) $batch->batch_id;

            if (isset($seen[$id])) {
                return;
            }

            $seen[$id] = true;

            $parentId = self::parentId($batch);
            $parentCode = null;
            $parentMissing = false;

            if ($parentId !== null) {
                if (isset($byId[$parentId])) {
                    $parentCode = (string) $byId[$parentId]->batch_code;
                } else {
                    $parentCode = $missingParentCodes[$parentId]
                        ?? ($batch->relationLoaded('parent') ? $batch->parent?->batch_code : null);
                    $parentMissing = true;
                }
            }

            $nodes[] = [
                'batch' => $batch,
                'depth' => $depth,
                'parent_code' => $parentCode,
                'parent_missing' => $parentMissing,
                'ancestors' => $ancestors,
            ];

            $childIds = $children[$id] ?? [];
            $childBatches = array_map(fn (int $childId) => $byId[$childId], $childIds);

            usort($childBatches, self::compareByCode(...));

            $childAncestors = [...$ancestors, $id];

            foreach ($childBatches as $child) {
                $append($child, $depth + 1, $childAncestors);
            }
        };

        foreach ($roots as $root) {
            $append($root, 0, []);
        }

        // Safety net: never drop a row, even on an unexpected cycle.
        foreach ($byId as $id => $batch) {
            if (! isset($seen[$id])) {
                $append($batch, 0, []);
            }
        }

        return $nodes;
    }

    /**
     * Group the flattened list into one entry per root family, each carrying the
     * root node first followed by its descendants in order.
     *
     * @param  iterable<int,Batch>  $batches
     * @return list<array{root:Batch,root_node:array<string,mixed>,rows:list<array<string,mixed>>}>
     */
    public static function groups(iterable $batches): array
    {
        $groups = [];

        foreach (self::flatten($batches) as $node) {
            if ($node['depth'] === 0 || $groups === []) {
                $groups[] = [
                    'root' => $node['batch'],
                    'root_node' => $node,
                    'rows' => [],
                ];
            }

            $groups[array_key_last($groups)]['rows'][] = $node;
        }

        return $groups;
    }

    private static function parentId(Batch $batch): ?int
    {
        return $batch->parent_batch_id !== null ? (int) $batch->parent_batch_id : null;
    }

    private static function compareRoots(Batch $a, Batch $b): int
    {
        $aDate = $a->grafted_date?->format('Y-m-d') ?? '';
        $bDate = $b->grafted_date?->format('Y-m-d') ?? '';

        return ($aDate <=> $bDate) ?: strcmp((string) $a->batch_code, (string) $b->batch_code);
    }

    private static function compareByCode(Batch $a, Batch $b): int
    {
        return strcmp((string) $a->batch_code, (string) $b->batch_code);
    }
}
