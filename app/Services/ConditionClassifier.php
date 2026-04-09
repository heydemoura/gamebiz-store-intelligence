<?php

namespace App\Services;

use App\Enums\GameCondition;

class ConditionClassifier
{
    /**
     * Ordered from most specific to least specific to avoid false matches.
     *
     * @var array<string, GameCondition>
     */
    private array $keywords = [
        'recondicionado' => GameCondition::Good,
        'refurbished' => GameCondition::Good,
        'seminovo' => GameCondition::LikeNew,
        'semi-novo' => GameCondition::LikeNew,
        'semi novo' => GameCondition::LikeNew,
        'como novo' => GameCondition::LikeNew,
        'excelente estado' => GameCondition::LikeNew,
        'lacrado' => GameCondition::New,
        'selado' => GameCondition::New,
        'novo' => GameCondition::New,
        'bom estado' => GameCondition::Good,
        'bem conservado' => GameCondition::Good,
        'conservado' => GameCondition::Good,
        'mau estado' => GameCondition::Poor,
        'danificado' => GameCondition::Poor,
        'defeito' => GameCondition::Poor,
        'usado' => GameCondition::Fair,
    ];

    public function classify(string $text): GameCondition
    {
        $normalized = mb_strtolower($this->stripAccents($text));

        foreach ($this->keywords as $keyword => $condition) {
            if (str_contains($normalized, $keyword)) {
                return $condition;
            }
        }

        return GameCondition::Unknown;
    }

    private function stripAccents(string $text): string
    {
        $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');

        return $transliterator ? $transliterator->transliterate($text) : $text;
    }
}
