<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Zestaw zadań do wstawienia jednym kliknięciem (np. „Nowa kampania”, „Miesięczna obsługa”). */
class TaskSet extends Model
{
    protected $fillable = ['name', 'body'];

    public function lines(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $this->body)), fn ($l) => $l !== ''));
    }
}
