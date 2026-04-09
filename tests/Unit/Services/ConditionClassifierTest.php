<?php

namespace Tests\Unit\Services;

use App\Enums\GameCondition;
use App\Services\ConditionClassifier;
use PHPUnit\Framework\TestCase;

class ConditionClassifierTest extends TestCase
{
    private ConditionClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new ConditionClassifier;
    }

    public function testClassifiesNewCondition(): void
    {
        $this->assertSame(GameCondition::New, $this->classifier->classify('Jogo GTA V PS4 Novo Lacrado'));
        $this->assertSame(GameCondition::New, $this->classifier->classify('God of War PS5 lacrado'));
    }

    public function testClassifiesLikeNewCondition(): void
    {
        $this->assertSame(GameCondition::LikeNew, $this->classifier->classify('Jogo Zelda Switch Seminovo'));
        $this->assertSame(GameCondition::LikeNew, $this->classifier->classify('FIFA 24 PS5 Semi-novo'));
        $this->assertSame(GameCondition::LikeNew, $this->classifier->classify('Red Dead Redemption como novo'));
    }

    public function testClassifiesGoodCondition(): void
    {
        $this->assertSame(GameCondition::Good, $this->classifier->classify('Horizon Zero Dawn PS4 bom estado'));
        $this->assertSame(GameCondition::Good, $this->classifier->classify('Mario Kart Switch bem conservado'));
    }

    public function testClassifiesFairCondition(): void
    {
        $this->assertSame(GameCondition::Fair, $this->classifier->classify('Call of Duty PS4 usado'));
    }

    public function testClassifiesPoorCondition(): void
    {
        $this->assertSame(GameCondition::Poor, $this->classifier->classify('Jogo com defeito FIFA 23'));
        $this->assertSame(GameCondition::Poor, $this->classifier->classify('CD danificado Uncharted'));
    }

    public function testClassifiesUnknownWhenNoKeywords(): void
    {
        $this->assertSame(GameCondition::Unknown, $this->classifier->classify('Spider-Man PS5'));
        $this->assertSame(GameCondition::Unknown, $this->classifier->classify('The Last of Us Part II'));
    }

    public function testHandlesAccentedCharacters(): void
    {
        $this->assertSame(GameCondition::Good, $this->classifier->classify('Jogo em bom estado'));
    }

    public function testIsCaseInsensitive(): void
    {
        $this->assertSame(GameCondition::New, $this->classifier->classify('LACRADO'));
        $this->assertSame(GameCondition::LikeNew, $this->classifier->classify('SEMINOVO'));
    }
}
