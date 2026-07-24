<div x-data="{ copied: false }" style="display: grid; gap: .75rem;">
    <p style="font-size: .875rem; opacity: .75;">
        Qualquer pessoa com este link vê o relatório de <strong>{{ $year }}</strong> desta
        carteira (bens, proventos, vendas e DARF) — nada além disso. O link expira em
        <strong>45 dias</strong>.
    </p>

    <div style="display: flex; gap: .5rem; align-items: center;">
        <input type="text" readonly value="{{ $url }}" x-ref="link" x-on:focus="$el.select()"
               style="flex: 1; font-size: .75rem; border-radius: .5rem; border: 1px solid rgba(128,128,128,.35); background: transparent; padding: .5rem .75rem;"/>
        <x-filament::button color="primary" size="sm"
            x-on:click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 2000)">
            <span x-show="! copied">Copiar</span>
            <span x-show="copied" x-cloak>Copiado ✓</span>
        </x-filament::button>
    </div>

    <p style="font-size: .75rem; opacity: .55;">
        Enviou por engano? O link deixa de funcionar sozinho no vencimento; para revogar antes,
        fale com o suporte ({{ config('landing.contact.email') }}).
    </p>
</div>
