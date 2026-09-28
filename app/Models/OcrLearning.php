<?php
namespace App\Models;

use App\Core\Model;

/**
 * OcrLearning — makes the receipt scanner improve over time.
 *
 * There is no machine learning model here, and that is deliberate.
 * Training a document model needs thousands of labelled samples; the
 * temple writes a few hundred receipts a year. Until that data exists,
 * a correction log plus two lookups beats a model that was trained on
 * too little — and the log is what collects the data in the meantime.
 *
 * Three jobs:
 *   record()      — store what OCR guessed vs what the human saved
 *   learnedFix()  — apply a correction that has proven itself
 *   suggestNames()— recognise a donor who has given before
 *
 * Plus accuracy(), which tells you which field is worth fixing next
 * instead of guessing.
 */
class OcrLearning extends Model
{
    /**
     * How many times the same correction must be seen before it is
     * applied automatically.
     *
     * Two is too eager — the same volunteer making the same typo twice
     * in one sitting would teach the system a mistake. Three means it
     * has held up across different receipts.
     */
    private const CONFIRMATIONS_NEEDED = 3;

    /**
     * Fields where an automatic correction is safe.
     *
     * Amounts and reference numbers come from a small, fixed alphabet,
     * so 'O' really is always '0'. NAMES ARE DELIBERATELY ABSENT: two
     * different donors can legitimately OCR to similar text, and
     * silently rewriting one person's name into another's would corrupt
     * the donation record — the one thing in this system that must not
     * be quietly wrong.
     */
    /**
     * Where past donor names live: [table, name column, amount column].
     *
     * Kept here as one constant because it is the single thing most
     * likely to differ from your database. If the receipt screen stores
     * its own rows in a `receipts` table, point this at that instead —
     * nothing else in this class needs changing.
     */
    private const NAME_HISTORY = ['donations', 'name', 'amount'];

    private const AUTOFIX_FIELDS = [
        'receipt_no', 'amount', 'total', 'donation', 'blessing',
        'lotus_lamp', 'oil_offering', 'dragon_incense', 'tower_incense',
        'contribution', 'meal_offering', 'other', 'date',
    ];

    // ------------------------------------------------------------------
    // 1. Recording — call this when the human saves the review form
    // ------------------------------------------------------------------

    /**
     * Log every field of one reviewed document.
     *
     * Pass the two arrays keyed the same way: what the scanner produced,
     * and what was actually saved. Fields present in $final but missing
     * from $ocr are logged with a NULL ocr_value, because "the scanner
     * found nothing" is a different failure from "the scanner was wrong"
     * and they need different fixes.
     *
     * @return int how many fields the human had to correct
     */
    public function record(
        int $docId,
        array $ocr,
        array $final,
        string $correctedBy,
        ?string $imagePath = null,
        string $docType = 'receipt'
    ): int {
        $wrongCount = 0;

        foreach ($final as $field => $finalValue) {
            $ocrValue = $ocr[$field] ?? null;

            $ocrNorm   = $this->normalise($ocrValue);
            $finalNorm = $this->normalise($finalValue);

            // Both empty means the field was simply unused on this
            // receipt — logging it would bury the real signal under
            // thousands of blank rows.
            if ($ocrNorm === '' && $finalNorm === '') {
                continue;
            }

            $wasWrong = ($ocrNorm !== $finalNorm) ? 1 : 0;
            $wrongCount += $wasWrong;

            $this->execute(
                'INSERT INTO ocr_corrections
                    (doc_type, doc_id, field_name, ocr_value, final_value,
                     was_wrong, image_path, corrected_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $docType,
                    $docId,
                    (string) $field,
                    $ocrValue === null ? null : mb_substr((string) $ocrValue, 0, 255),
                    $finalValue === null ? null : mb_substr((string) $finalValue, 0, 255),
                    $wasWrong,
                    $imagePath,
                    $correctedBy,
                ]
            );
        }

        return $wrongCount;
    }

    // ------------------------------------------------------------------
    // 2. The correction dictionary — the part that "learns"
    // ------------------------------------------------------------------

    /**
     * Has this exact OCR mistake been corrected the same way before?
     *
     * Returns the corrected value, or null to leave the OCR text alone.
     *
     * Two conditions, both required:
     *   - it has been corrected the same way at least CONFIRMATIONS_NEEDED times
     *   - it has NEVER been corrected to anything else
     *
     * The second condition is the important one. If '50P' became '50'
     * five times and '500' once, the mapping is ambiguous and applying
     * it would silently invent a donation amount. Ambiguity means hand
     * it back to the human.
     */
    public function learnedFix(string $field, ?string $ocrValue): ?string
    {
        $ocrValue = $this->normalise($ocrValue);

        if ($ocrValue === '' || !in_array($field, self::AUTOFIX_FIELDS, true)) {
            return null;
        }

        $rows = $this->fetchAll(
            "SELECT final_value, COUNT(*) AS seen
             FROM ocr_corrections
             WHERE field_name = ? AND ocr_value = ? AND was_wrong = 1
             GROUP BY final_value
             ORDER BY seen DESC",
            [$field, $ocrValue]
        );

        if (count($rows) !== 1) {
            return null;           // never corrected, or corrected inconsistently
        }

        return ((int) $rows[0]['seen'] >= self::CONFIRMATIONS_NEEDED)
            ? (string) $rows[0]['final_value']
            : null;
    }

    /**
     * Apply every learned fix to a freshly scanned set of fields.
     *
     * Returns [$fields, $applied] — the corrected values, and a list of
     * what was changed. SHOW THAT LIST TO THE HUMAN. A system that
     * silently rewrites what it read is one the committee cannot audit,
     * and the first time it rewrites something wrongly they stop
     * trusting all of it.
     */
    public function applyLearnedFixes(array $fields): array
    {
        $applied = [];

        foreach ($fields as $name => $value) {
            $fix = $this->learnedFix((string) $name, $value === null ? null : (string) $value);
            if ($fix !== null && $fix !== $value) {
                $applied[$name] = ['from' => $value, 'to' => $fix];
                $fields[$name]  = $fix;
            }
        }

        return [$fields, $applied];
    }

    // ------------------------------------------------------------------
    // 3. Donor memory — the part users actually notice
    // ------------------------------------------------------------------

    /**
     * Donors whose name resembles what the scanner read.
     *
     * Names are never auto-applied (see AUTOFIX_FIELDS); these are
     * offered as a pick list. To the committee this reads as "the system
     * remembers people", which is the single highest ratio of perceived
     * intelligence to code in the whole project. It is one query.
     *
     * Matching is deliberately loose — OCR mangles handwriting — and
     * ordered by how often that donor has given, so regulars surface
     * first.
     *
     * @return array<int, array{name: string, times: int, last_amount: string}>
     */
    public function suggestNames(?string $ocrName, int $limit = 5): array
    {
        $ocrName = $this->normalise($ocrName);
        if (mb_strlen($ocrName) < 3) {
            return [];
        }

        // The first run of letters is the most reliable part of an OCR'd
        // name: 'CTS SERVICES PLT (IMR.See)' and 'CTS SERVICES PLT (MR.
        // See)' differ only in the noisy tail.
        $head = preg_split('/[\s(,.]+/u', $ocrName)[0] ?? $ocrName;
        $head = mb_substr($head, 0, 12);

        [$table, $nameCol, $amountCol] = self::NAME_HISTORY;

        // Identifiers cannot be bound as parameters, so they come from
        // the constant above and never from user input.
        return $this->fetchAll(
            "SELECT `$nameCol` AS name,
                    COUNT(*)              AS times,
                    MAX(`$amountCol`)     AS last_amount
             FROM `$table`
             WHERE `$nameCol` LIKE ? OR SOUNDEX(`$nameCol`) = SOUNDEX(?)
             GROUP BY `$nameCol`
             ORDER BY times DESC, name
             LIMIT " . (int) $limit,
            ['%' . $head . '%', $ocrName]
        );
    }

    // ------------------------------------------------------------------
    // 4. Accuracy — what to fix next
    // ------------------------------------------------------------------

    /**
     * Error rate per field, worst first.
     *
     * This is the report that decides where effort goes. A field at 60%
     * is worth a day's work; a field at 4% is not, however annoying it
     * feels the day it goes wrong.
     */
    public function accuracy(string $docType = 'receipt'): array
    {
        return $this->fetchAll(
            "SELECT field_name,
                    COUNT(*)                                   AS scanned,
                    SUM(was_wrong)                             AS corrected,
                    ROUND(100 * SUM(was_wrong) / COUNT(*), 1)  AS error_pct,
                    SUM(ocr_value IS NULL OR ocr_value = '')   AS found_nothing
             FROM ocr_corrections
             WHERE doc_type = ?
             GROUP BY field_name
             ORDER BY error_pct DESC, scanned DESC",
            [$docType]
        );
    }

    /**
     * Corrections seen often enough to be worth turning into a rule,
     * including the ones still short of the threshold — useful for
     * seeing what the system is about to learn before it does.
     */
    public function commonMistakes(int $minSeen = 2, int $limit = 40): array
    {
        // auto_applied has to test the field whitelist as well as the
        // count, or the report claims a name correction is being applied
        // automatically when learnedFix() would always refuse it. A
        // report that overstates what the system does is worse than no
        // report: it is what people check when they stop trusting it.
        $placeholders = implode(',', array_fill(0, count(self::AUTOFIX_FIELDS), '?'));

        return $this->fetchAll(
            "SELECT c.field_name, c.ocr_value, c.final_value, COUNT(*) AS seen,
                    (
                      COUNT(*) >= ?
                      AND c.field_name IN ($placeholders)
                      AND (SELECT COUNT(DISTINCT c2.final_value)
                           FROM ocr_corrections c2
                           WHERE c2.field_name = c.field_name
                             AND c2.ocr_value  = c.ocr_value
                             AND c2.was_wrong  = 1) = 1
                    ) AS auto_applied
             FROM ocr_corrections c
             WHERE c.was_wrong = 1 AND c.ocr_value <> ''
             GROUP BY c.field_name, c.ocr_value, c.final_value
             HAVING seen >= ?
             ORDER BY seen DESC, c.field_name
             LIMIT " . (int) $limit,
            array_merge(
                [self::CONFIRMATIONS_NEEDED],
                self::AUTOFIX_FIELDS,
                [$minSeen]
            )
        );
    }

    /** How many documents have been reviewed — the size of the training set. */
    public function sampleCount(string $docType = 'receipt'): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(DISTINCT doc_id) FROM ocr_corrections WHERE doc_type = ?',
            [$docType]
        );
    }

    // ------------------------------------------------------------------

    /**
     * Compare like with like.
     *
     * 'RM 50.00' and '50.00' are the same answer typed differently, and
     * counting that as an OCR error would inflate the error rate until
     * the report is useless. Case and spacing are noise; digits are not.
     */
    private function normalise($value): string
    {
        if ($value === null) {
            return '';
        }

        $v = trim((string) $value);
        $v = preg_replace('/\s+/u', ' ', $v);
        $v = preg_replace('/^(RM|rm)\s*/u', '', $v);

        // Trailing zeros on money: 50, 50.0 and 50.00 are one value.
        if (is_numeric(str_replace(',', '', $v))) {
            $v = rtrim(rtrim(number_format((float) str_replace(',', '', $v), 2, '.', ''), '0'), '.');
        }

        return mb_strtoupper($v);
    }
}
