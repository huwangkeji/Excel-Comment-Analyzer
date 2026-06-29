<?php
/**
 * DataCleaner - Comment data cleaning
 * Removes HTML tags, extra spaces, special chars, etc.
 */

class DataCleaner
{
    /**
     * Clean a single comment string
     */
    public static function cleanComment($text)
    {
        if (empty($text) || !is_string($text)) {
            return '';
        }

        // Remove HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove control characters (keep newlines, tabs)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Normalize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove excessive newlines (more than 2)
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        // Replace multiple spaces with single space
        $text = preg_replace('/[ \t]+/', ' ', $text);

        // Remove spaces at beginning/end of lines
        $text = preg_replace('/^ +| +$/m', '', $text);

        // Remove excessive punctuation repetition (!!!, ???, 。。。 etc)
        $text = preg_replace('/([!?！？。，,])\1{2,}/', '$1$1', $text);

        // Trim
        $text = trim($text);

        return $text;
    }

    /**
     * Check if comment is valid (not empty, not spam)
     */
    public static function isValidComment($text)
    {
        $text = trim($text);
        if (empty($text)) return false;
        if (mb_strlen($text) < 1) return false;

        // Check for pure number
        if (is_numeric($text)) return false;

        // Check for pure punctuation/symbols
        if (preg_match('/^[\p{P}\p{S}\s]+$/u', $text)) return false;

        // Check for single emoji
        if (mb_strlen($text) <= 2 && preg_match('/^[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]+$/u', $text)) {
            return false;
        }

        return true;
    }

    /**
     * Clean numeric value (likes, etc.)
     */
    public static function cleanNumber($value)
    {
        if (empty($value)) return 0;
        $value = trim((string)$value);
        // Remove non-numeric chars except decimal point and negative sign
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        return is_numeric($value) ? (float)$value : 0;
    }

    /**
     * Clean datetime string
     */
    public static function cleanDateTime($value)
    {
        if (empty($value)) return '';
        $value = trim((string)$value);

        // Try to parse common date formats
        $ts = strtotime($value);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d H:i:s', $ts);
        }

        // Try Excel serial date
        if (is_numeric($value) && $value > 1 && $value < 100000) {
            $unix = ($value - 25569) * 86400;
            if ($unix > 0 && $unix < 4102444800) {
                return date('Y-m-d H:i:s', (int)$unix);
            }
        }

        return $value;
    }
}
