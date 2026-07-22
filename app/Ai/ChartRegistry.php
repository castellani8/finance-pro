<?php

namespace App\Ai;

/**
 * Ponte entre a tool GerarGrafico e o chat: a tool roda dentro do prompt()
 * e empilha specs aqui; depois que a resposta volta, o componente Livewire
 * drena a pilha e renderiza os SVGs. Singleton com escopo de request.
 */
class ChartRegistry
{
    /** @var array<int, array<string, mixed>> */
    private array $charts = [];

    /** @param array<string, mixed> $spec */
    public function push(array $spec): void
    {
        $this->charts[] = $spec;
    }

    /** @return array<int, array<string, mixed>> */
    public function flush(): array
    {
        return tap($this->charts, fn () => $this->charts = []);
    }
}
