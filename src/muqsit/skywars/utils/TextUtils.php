<?php
/**
 * Ported from MiNET
 * https://github.com/NiclasOlofsson/MiNET/blob/master/src/MiNET/MiNET/Utils/TextUtils.cs
 */

declare(strict_types=1);
namespace muqsit\skywars\utils;

use pocketmine\utils\TextFormat;

class TextUtils {

    public const LINE_LENGTH = 36;
    public const CHAR_WIDTH = 6;

    public const SPACE_CHAR = ' ';

    const CLEAN_ALL_FORMATTING_FILTER = "/(?:&|§)([0123456789abcdefklmnor])/i";

    const BOLD_TEXT_REGEX = "/(?:&|§)l(.+?)(?:[&|§]r|$)/i";

    public const CHAR_WIDTHS = [
        ' ' => 4,
        '!' => 2,
        '"' => 5,
        '\'' => 3,
        '(' => 5,
        ')' => 5,
        '*' => 5,
        ',' => 2,
        '.' => 2,
        ':' => 2,
        ';' => 2,
        '<' => 5,
        '>' => 5,
        '@' => 7,
        'I' => 4,
        '[' => 4,
        ']' => 4,
        'f' => 5,
        'i' => 2,
        'k' => 5,
        'l' => 3,
        't' => 4,
        '{' => 5,
        '|' => 2,
        '}' => 5,
        '~' => 7,
        '█' => 9,
        '░' => 8,
        '▒' => 9,
        '▓' => 9,
        '▌' => 5,
        '─' => 9
    ];

    public static function centerLine(string $input) : string
    {
        return self::center($input, self::LINE_LENGTH * self::CHAR_WIDTH);
    }

    public static function center(string $input, int $maxLength = 0, bool $addRightPadding = false) : string
    {
        $lines = explode("\n", trim($input));

        $sortedLines = $lines;
        usort($sortedLines, function(string $a, string $b) {
            return TextUtils::getPixelLength($b) <=> TextUtils::getPixelLength($a);
        });

        $longest = $sortedLines[0];

        if ($maxLength === 0) {
            $maxLength = TextUtils::getPixelLength($longest);
        }

        $result = "";

        $spaceWidth_2 = TextUtils::getCharWidth(TextUtils::SPACE_CHAR) * 2;

        foreach ($lines as $sortedLine) {
            $len = max($maxLength - TextUtils::getPixelLength($sortedLine), 0);
            $len_spacew = $len / $spaceWidth_2;

            $padding = (int) round($len_spacew);
            $paddingRight = (int) floor($len_spacew);

            $result .= str_pad(TextUtils::SPACE_CHAR, $padding) . $sortedLine . TextFormat::RESET . ($addRightPadding ? str_pad(TextUtils::SPACE_CHAR, $paddingRight) : "") . "\n";
        }

        $result = rtrim($result, "\n");

        return $result;
    }

    private static function getCharWidth(string $c) : int
    {
        return TextUtils::CHAR_WIDTHS[$c] ?? TextUtils::CHAR_WIDTH;
    }

    public static function getPixelLength(string $line) : int
    {
        $length = 0;
        foreach (str_split(TextFormat::clean($line)) as $c) {
            $length += TextUtils::getCharWidth($c);
        }

        // +1 for each bold character
        if (preg_match(TextUtils::BOLD_TEXT_REGEX, $line, $boldMatches) > 0) {
            foreach ($boldMatches as $boldText) {
                $length += strlen(preg_replace(TextUtils::CLEAN_ALL_FORMATTING_FILTER, "", $boldText[0]));
            }
        }

        $length += substr_count($line, TextFormat::BOLD);
        return $length;
    }
}
