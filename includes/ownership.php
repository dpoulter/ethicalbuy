<?php
/**
 * The Ownership dimension, scored from a brand's recorded ownership type
 * (brands.type). Required from includes/scoring.php.
 *
 * The categories and their points below are value judgements, not facts.
 * They are Dale's to change, and this table is the only place they live: the
 * scoring script, the brand and product pages and the About page all read it.
 */

const OWNERSHIP_SOURCE = "type-mapping";

/**
 * Ownership categories, most to least favoured. Each has a key, a plain
 * label, the points it scores (1-10), a one-line meaning, and the recorded
 * type values that count as it.
 *
 * Types are matched exactly after search_key() normalisation (case, accents
 * and punctuation ignored), never by substring, so "Independent ..." and
 * "Private ..." strings cannot drift into the wrong category. A type not
 * listed here is left unscored rather than guessed: add it to a list.
 */
function ownership_categories()
{
    return [
        [
            "key" => "social_enterprise", "label" => "Independent / social enterprise", "points" => 10,
            "meaning" => "Independent and run for a social purpose.",
            "types" => ["Independent (Social Enterprise)"],
        ],
        [
            "key" => "cooperative", "label" => "Co-operative", "points" => 9,
            "meaning" => "Owned by its members.",
            "types" => ["Co-operative", "Irish Co-operative"],
        ],
        [
            "key" => "independent", "label" => "Independent company", "points" => 8,
            "meaning" => "A company not owned by a larger group.",
            "types" => ["Independent UK Company", "Independent US Company"],
        ],
        [
            "key" => "family", "label" => "Family-owned", "points" => 8,
            "meaning" => "Controlled by a family rather than outside shareholders.",
            "types" => ["Family-Owned", "Family-Owned UK Company", "German Family-Owned"],
        ],
        [
            "key" => "private", "label" => "Private company", "points" => 6,
            "meaning" => "Privately held, ownership not otherwise recorded.",
            "types" => ["Private Company", "Private UK Company", "Private Austrian Company", "Private Irish Company"],
        ],
        [
            "key" => "listed", "label" => "Listed company", "points" => 4,
            "meaning" => "Shares traded on a stock exchange.",
            "types" => ["UK Public Company", "Public Company", "Publicly Traded UK Company"],
        ],
        [
            "key" => "own_brand", "label" => "Retailer own brand", "points" => 3,
            "meaning" => "A label belonging to a supermarket or other retailer.",
            "types" => ["Supermarket Own Brand", "Retail Own Brand"],
        ],
        [
            "key" => "private_equity", "label" => "Private equity owned", "points" => 3,
            "meaning" => "Owned by a private equity investor.",
            "types" => ["Private Equity"],
        ],
        [
            "key" => "subsidiary", "label" => "Subsidiary of a larger group", "points" => 3,
            "meaning" => "Part of a larger company group.",
            "types" => ["Subsidiary (US Corporation)", "Subsidiary (US Corp)", "Subsidiary of German Pharma Company"],
        ],
        [
            "key" => "multinational", "label" => "Multinational group", "points" => 2,
            "meaning" => "Part of a large group operating across many countries.",
            "types" => ["Multinational Corporation", "Swiss Corporation"],
        ],
    ];
}

/**
 * The category a recorded ownership type counts as, or null when the type is
 * not listed in ownership_categories().
 */
function ownership_category_for_type($type)
{
    static $index = null;

    if ($index === null)
    {
        $index = [];
        foreach (ownership_categories() as $category)
        {
            foreach ($category["types"] as $listed)
            {
                $index[search_key($listed)] = $category;
            }
        }
    }

    $key = search_key((string) $type);

    return $key === "" ? null : ($index[$key] ?? null);
}

/**
 * The note stored with a type-mapped score, e.g.
 * Recorded as "Family-Owned UK Company"; counted as Family-owned: 8
 */
function ownership_note($type, $category)
{
    return sprintf('Recorded as "%s"; counted as %s: %d', trim((string) $type), $category["label"], $category["points"]);
}
