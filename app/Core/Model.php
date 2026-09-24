<?php
namespace App\Core;

use PDO;

/**
 * Model — base class for every model.
 *
 * Gives each model a ready PDO handle in $this->db plus two small
 * helpers that every table in this project needs.
 */
abstract class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::conn();
    }

    /**
     * Give a freshly inserted row its human-readable reference code,
     * e.g. RSVP-0001, DON-0007.
     *
     * The code is derived from the row's OWN auto-increment id, which is
     * why this runs after the INSERT rather than before it. Reading
     * MAX(id) beforehand would be wrong twice over: deleted rows leave
     * gaps that shift every later code, and two visitors submitting at
     * the same moment would both read the same MAX and fight over one
     * code. Letting MySQL hand out the id removes both problems.
     */
    protected function assignRefCode(string $table, string $prefix, int $id): string
    {
        $code = $prefix . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        $this->execute("UPDATE {$table} SET ref_code = ? WHERE id = ?", [$code, $id]);
        return $code;
    }

    /** Run a prepared SELECT and return all rows. */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Run a prepared SELECT and return the first row (or null). */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Run a prepared INSERT/UPDATE/DELETE, return affected row count. */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Restart a table's auto-increment at 1, but ONLY when the table is
     * empty. Used after clearing test data so the first real record does
     * not inherit the numbers a dry run used up.
     *
     * The emptiness check is what makes this safe: resetting a counter on
     * a table that still holds rows would hand out ids that already exist.
     */
    protected function resetAutoIncrementIfEmpty(string $table): bool
    {
        $count = (int) $this->scalar("SELECT COUNT(*) FROM {$table}");
        if ($count > 0) {
            return false;
        }
        // Table name is supplied by our own code, never by a request.
        $this->db->exec("ALTER TABLE {$table} AUTO_INCREMENT = 1");
        return true;
    }

    /** Run a scalar aggregate query, e.g. COUNT/SUM. */
    protected function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
