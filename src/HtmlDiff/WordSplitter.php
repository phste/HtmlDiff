<?php namespace HtmlDiff;

use Exception;

class WordSplitter
{

    /**
     * Converts Html text into a list of words
     * @throws Exception
     */
    public static function convertHtmlToListOfWords(string $text, array $blockExpressions)
    {
        $mode = Mode::CHARACTER;
        $currentWord = "";
        /** @var string[] $words */
        $words = [];
        $blockLocations = static::findBlocks($text, $blockExpressions);
        $isBlockCheckRequired = count($blockLocations) > 0;
        $isGrouping = false;
        $groupingUntil = -1;

        for ($index = 0; $index < mb_strlen($text); $index++)
        {
            $character = mb_substr($text, $index, 1);

            // Don't bother executing block checks if we don't have any blocks to check for!
            if ($isBlockCheckRequired) {
                // Check if we have completed grouping a text sequence/block
                if ($groupingUntil === $index) {
                    $groupingUntil = -1;
                    $isGrouping = false;
                }

                // Check if we need to group the next text sequence/block
                $until = $blockLocations[$index] ?? 0;
                if (isset($blockLocations[$index])) {
                    $isGrouping = true;
                    $groupingUntil = $until;
                }

                // if we are grouping, then we don't care about what type of character we have,
                // it's going to be treated as a word
                if ($isGrouping) {
                    $currentWord .= $character;
                    $mode = Mode::CHARACTER;
                    continue;
                }
            }

            switch ($mode) {
                case Mode::CHARACTER:
                    if (Utils::isStartOfTag($character)) {
                        if (mb_strlen($currentWord) !== 0) {
                            $words[] = $currentWord;
                        }
                        $currentWord = "<";
                        $mode = Mode::TAG;
                    } else if (Utils::isStartOfEntity($character)) {
                        if (mb_strlen($currentWord) !== 0) {
                            $words[] = $currentWord;
                        }
                        $currentWord = $character;
                        $mode = Mode::ENTITY;
                    } else if (Utils::isWhiteSpace($character)) {
                        if (mb_strlen($currentWord) !== 0) {
                            $words[] = $currentWord;
                        }
                        $currentWord = $character;
                        $mode = Mode::WHITESPACE;
                    } else if (Utils::isWord($character) &&
                        (mb_strlen($currentWord) === 0) || Utils::isWord(substr($currentWord, -1))) {
                        $currentWord .= $character;
                    } else {
                        if (mb_strlen($currentWord) !== 0) {
                            $words[] = $currentWord;
                        }
                        $currentWord = $character;
                    }

                    break;
                case Mode::TAG:

                    if (Utils::isEndOfTag($character)) {
                        $currentWord .= $character;
                        $words[] = $currentWord;
                        $currentWord = "";

                        $mode = Utils::isWhiteSpace($character) ? Mode::WHITESPACE : Mode::CHARACTER;
                    } else {
                        $currentWord[] = $character;
                    }

                    break;
                case Mode::WHITESPACE:

                    if (Utils::isStartOfTag($character))
                    {
                        if (mb_strlen($currentWord) !== 0) {
                            $words[] = $currentWord;
                        }
                        $currentWord = $character;
                        $mode = Mode::TAG;
                    } else if (Utils::isStartOfEntity($character)) {
                        if (mb_strlen($currentWord) !== 0) {
                            $words[] = $currentWord;
                        }
                        $currentWord = $character;
                        $mode = Mode::ENTITY;
                    } else if (Utils::isWhiteSpace($character)) {
                        $currentWord .= $character;
                    } else {
                        if (mb_strlen($currentWord) !== 0) {
                            $words[] = $currentWord;
                        }
                        $currentWord = $character;
                        $mode = Mode::CHARACTER;
                    }

                    break;
                case Mode::ENTITY:

                    if (Utils::isStartOfTag($character))
                    {
                        if (mb_strlen($currentWord) !== 0) {
                            $words[] = $currentWord;
                        }
                        $currentWord = $character;
                        $mode = Mode::TAG;
                    } else if (Utils::isWhiteSpace($character)) {
                        if (mb_strlen($currentWord) !== 0) {
                            $words[] = $currentWord;
                        }
                        $currentWord = $character;
                        $mode = Mode::WHITESPACE;
                    } else if (Utils::isEndOfEntity($character)) {
                        $switchToNextMode = true;
                        if (mb_strlen($currentWord) !== 0) {
                            $currentWord .= $character;
                            $words[] = $currentWord;

                            //join &nbsp; entity with last whitespace
                            if (count($words) > 2
                                && Utils::isWhiteSpace($words[count($words) - 2])
                                && Utils::isWhiteSpace($words[count($words) - 1])) {
                                $w1 = $words[count($words) - 2];
                                $w2 = $words[count($words) - 1];
                                $words = array_slice($words, 0, count($words) - 2);
                                $currentWord = $w1 . $w2;
                                $mode = Mode::WHITESPACE;
                                $switchToNextMode = false;
                            }
                        }
                        if ($switchToNextMode) {
                            $currentWord = "";
                            $mode = Mode::CHARACTER;
                        }
                    } else if (Utils::isWord($character)) {
                        $currentWord .= $character;
                    } else {
                        if (mb_strlen($currentWord) !== 0) {
                            $words[] = $currentWord;
                        }
                        $currentWord = $character;
                        $mode = Mode::CHARACTER;
                    }
                    break;
            }
        }

        if (mb_strlen($currentWord) !== 0) {
            $words[] = $currentWord;
        }

        return $words;
    }

    /**
     * Finds any blocks that need to be grouped.
     */
    private static function findBlocks(string $text, array $blockExpressions = null): array
    {
        /** @var int[] $blockLocations */
        $blockLocations = [];

        if (is_null($blockExpressions)) {
            return $blockLocations;
        }

        foreach ($blockExpressions as $exp) {
            $matches = [];
            preg_match_all($exp, $text, $matches);
            foreach ($matches[0] as $index => $match) {
                if (isset($blockLocations[$index])) {
                    throw new Exception("One or more block expressions result in a text sequence that overlaps. Current expression: {$exp}");
                }

                $blockLocations[$index] = $index + mb_strlen($match);
            }
        }

        return $blockLocations;
    }

}