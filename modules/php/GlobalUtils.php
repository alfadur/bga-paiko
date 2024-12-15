<?php

trait GlobalUtils
{
    private function inState(int $state): bool
    {
        return (int)$this->gamestate->state_id() === $state;
    }

    private function get(\GameGlobal $global): int
    {
        $value = $this->instGlobals[$global->value] ?? null;
        if ($value === null) {
            $value = $this->getGameStateValue($global->value);
            $this->instGlobals[$global->value] = $value;
        }
        return $value;
    }

    private function getBits(\GameGlobal $global, int $size, int $index): int
    {
        $value = $this->get($global);
        return $value >> $index * $size & (1 << $size) - 1;
    }

    private function set(\GameGlobal $global, int $value): void {
        $this->instGlobals[$global->value] = $value;
    }

    private function preInc(\GameGlobal $global, int $amount = 1): int {
        $value = $this->instGlobals[$global->value]
            ?? $this->getGameStateValue($global->value);
        $this->instGlobals[$global->value] = $value + $amount;
        return $value;
    }

    private function postInc(\GameGlobal $global, int $amount = 1): int {
        $value = $this->instGlobals[$global->value]
            ?? $this->getGameStateValue($global->value);
        $value += $amount;
        $this->instGlobals[$global->value] = $value;
        return $value;
    }

    private function optionEnabled(GameOption $option): bool
    {
        return (int)$this->getGameStateValue($option->value) !== 0;
    }

    private function getOption(\GameOption $option): int
    {
        return (int)$this->getGameStateValue($option->value);
    }

    private function initGlobals(): void
    {
        $args = [];

        foreach (GameGlobal::cases() as $name) {
            $id = GameGlobal::IDS[$name->value];
            $value = $this->instGlobals[$name->value] ?? 0;
            $args[] = "($id, $value)";
        }

        $args = implode(',', $args);

        self::DbQuery(<<<EOF
            INSERT INTO global(global_id, global_value) 
            VALUES $args
            EOF);
    }

    private function commitGlobals(): void
    {
        if (count($this->instGlobals) === 0) {
            return;
        }

        $ids = [];
        $setters = [];

        foreach ($this->instGlobals as $name => $value) {
            $id = GameGlobal::IDS[$name];
            $ids[] = $id;
            $setters[] = "WHEN $id THEN $value";
        }

        $ids = implode(',', $ids);
        $setters = implode(' ', $setters);

        self::DbQuery(<<<EOF
            UPDATE global
            SET global_value = CASE global_id $setters END  
            WHERE global_id IN ($ids)
            EOF);
    }
}
