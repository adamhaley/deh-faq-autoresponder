<?php

namespace App\Enums\Concerns;

trait TranslatesLabels
{
    public function getLabel(): string
    {
        return __('enums.'.static::class.'.'.$this->value);
    }
}
