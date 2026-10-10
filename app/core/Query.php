<?php

/**
 * One query for a whole list where a page would otherwise run one per row
 * (audit P7): the cart's lines, a customer's saved designs, an order's items.
 * $sql has a single %s where the id placeholders go: "… WHERE id IN (%s)".
 */
final class Query {
    /** The rows $sql finds for these ids; none, without a query, for no ids. */
    public static function forIds(PDO $db, string $sql, array $ids): array {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if (!$ids) {
            return [];
        }
        $stmt = $db->prepare(sprintf($sql, implode(',', array_fill(0, count($ids), '?'))));
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** forIds(), as lists keyed by each row's $key column, in the query's order. */
    public static function groupedBy(PDO $db, string $sql, array $ids, string $key): array {
        $groups = [];
        foreach (self::forIds($db, $sql, $ids) as $row) {
            $groups[$row[$key]][] = $row;
        }
        return $groups;
    }
}
