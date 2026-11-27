<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Pliki tłumaczeń: każdy się wczytuje, a polski i angielski mają te same klucze. */
class LangFilesTest extends TestCase
{
    public function test_pl_and_en_files_load_and_have_same_keys(): void
    {
        $flat = function (array $a, string $p = '') use (&$flat): array {
            $out = [];
            foreach ($a as $k => $v) {
                $out = array_merge($out, is_array($v) ? $flat($v, $p.$k.'.') : [$p.$k]);
            }

            return $out;
        };
        foreach (glob(lang_path('pl/*.php')) as $plFile) {
            $name = basename($plFile);
            if ($name === 'validation.php') {
                continue; // nazwy pól (attributes) potrzebne tylko po polsku
            }
            $pl = require $plFile;
            $en = require lang_path('en/'.$name);
            $this->assertIsArray($pl, $name);
            $this->assertIsArray($en, $name);
            $this->assertSame([], array_values(array_diff($flat($pl), $flat($en))), "Brak w en/{$name}");
            $this->assertSame([], array_values(array_diff($flat($en), $flat($pl))), "Brak w pl/{$name}");
        }
    }
}
