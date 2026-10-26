<?php

namespace App\Helpers;

/**
 * Scoring rules for dynamic (Gradescope-style) rubrics.
 *
 * A dynamic rubric is the usual rubric JSON plus:
 *   "scoring_style": "dynamic", "version": int, and an "id" on every item in "rubric_items".
 * A student's record stores which item ids were applied; the score is always recalculated
 * from the current item points, so editing an item flows through to every student who has it.
 */
class DynamicRubric
{
    /**
     * @param string|array|null $rubric
     * @return array|null
     */
    public static function decode($rubric): ?array
    {
        if (is_array($rubric)) {
            return $rubric;
        }
        if (!$rubric) {
            return null;
        }
        $decoded = json_decode($rubric, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param string|array|null $rubric
     * @return bool
     */
    public static function isDynamic($rubric): bool
    {
        $rubric = self::decode($rubric);
        return $rubric !== null && ($rubric['scoring_style'] ?? 'static') === 'dynamic';
    }

    /**
     * @param array $rubric
     * @return int
     */
    public static function version(array $rubric): int
    {
        return (int)($rubric['version'] ?? 0);
    }

    /**
     * @param array $rubric
     * @return array
     */
    public static function itemIds(array $rubric): array
    {
        $item_ids = [];
        foreach ($rubric['rubric_items'] ?? [] as $item) {
            if (isset($item['id'])) {
                $item_ids[] = (string)$item['id'];
            }
        }
        return $item_ids;
    }

    /**
     * Applied ids that are not items of this rubric.
     *
     * @param array $rubric
     * @param array $applied_item_ids
     * @return array
     */
    public static function unknownItemIds(array $rubric, array $applied_item_ids): array
    {
        return array_values(array_diff(array_map('strval', $applied_item_ids), self::itemIds($rubric)));
    }

    /**
     * The most the rubric part can be worth: the question's points minus the auto-graded score
     * (the same limit GradingController::_extraValidations enforces).
     *
     * @param $question_points
     * @param $auto_graded_score
     * @return float
     */
    public static function cap($question_points, $auto_graded_score): float
    {
        return max(0.0, (float)$question_points - (float)($auto_graded_score ?? 0));
    }

    /**
     * Builds a question's default dynamic rubric from the editor's items.
     * Items keep their id when it belongs to the previous version of this rubric; new items get a new id.
     * The version goes up by 1 (or starts at 1).
     *
     * @param array $items [{id?, title, description?, points}]
     * @param $rubric_shown
     * @param string|array|null $previous_rubric the question's rubric before this save
     * @return array
     */
    public static function prepareQuestionRubric(array $items, $rubric_shown, $previous_rubric): array
    {
        $previous_rubric = self::decode($previous_rubric);
        $previous_is_dynamic = self::isDynamic($previous_rubric);
        $previous_item_ids = $previous_is_dynamic ? self::itemIds($previous_rubric) : [];
        $used_ids = [];
        $rubric_items = [];
        foreach (array_values($items) as $item) {
            $id = isset($item['id']) ? (string)$item['id'] : '';
            if ($id === '' || !in_array($id, $previous_item_ids, true) || in_array($id, $used_ids, true)) {
                $id = self::newItemId(array_merge($previous_item_ids, $used_ids));
            }
            $used_ids[] = $id;
            $rubric_items[] = ['id' => $id,
                'title' => trim((string)($item['title'] ?? '')),
                'description' => trim((string)($item['description'] ?? '')),
                'points' => round((float)($item['points'] ?? 0), 2)];
        }
        return ['scoring_style' => 'dynamic',
            'version' => $previous_is_dynamic ? self::version($previous_rubric) + 1 : 1,
            'rubric_shown' => (bool)$rubric_shown,
            'rubric_items' => $rubric_items];
    }

    /**
     * A rubric as stored in a template: the scoring style is kept, item ids and the version are not
     * (loading a template always creates new items).
     *
     * @param array $items
     * @param $rubric_shown
     * @param string|null $scoring_style
     * @return array
     */
    public static function templateRubric(array $items, $rubric_shown, ?string $scoring_style): array
    {
        $template_items = [];
        foreach (array_values($items) as $item) {
            unset($item['id']);
            $template_items[] = $item;
        }
        $rubric = ['rubric_shown' => $rubric_shown, 'rubric_items' => $template_items];
        if ($scoring_style === 'dynamic') {
            $rubric = ['scoring_style' => 'dynamic'] + $rubric;
        }
        return $rubric;
    }

    /**
     * @param array $ids_in_use
     * @return string 8 lowercase hex characters, not in $ids_in_use
     */
    public static function newItemId(array $ids_in_use = []): string
    {
        do {
            $id = bin2hex(random_bytes(4));
        } while (in_array($id, $ids_in_use, true));
        return $id;
    }

    /**
     * raw     = sum of applied item points + adjustment
     * clamped = raw kept between 0 and the cap
     * final   = clamped minus the late penalty percentage, rounded to 2 decimals
     *
     * @param array $rubric
     * @param array $applied_item_ids
     * @param $adjustment
     * @param float $cap
     * @param $late_penalty_percent
     * @return array ['raw' => float, 'clamped' => float, 'final' => float]
     */
    public static function score(array $rubric, array $applied_item_ids, $adjustment, float $cap, $late_penalty_percent): array
    {
        $applied_item_ids = array_map('strval', $applied_item_ids);
        $raw = 0.0;
        foreach ($rubric['rubric_items'] ?? [] as $item) {
            if (isset($item['id']) && in_array((string)$item['id'], $applied_item_ids, true)) {
                $raw += (float)$item['points'];
            }
        }
        $raw += (float)($adjustment ?? 0);
        $clamped = min(max($raw, 0.0), $cap);
        $late_penalty_percent = min(max((float)($late_penalty_percent ?? 0), 0.0), 100.0);
        $final = round($clamped * (1 - $late_penalty_percent / 100), 2);
        return ['raw' => round($raw, 2), 'clamped' => round($clamped, 2), 'final' => $final];
    }
}
