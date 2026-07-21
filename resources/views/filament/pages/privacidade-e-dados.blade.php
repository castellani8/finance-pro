<x-filament-panels::page>
    @php($summary = $this->getDataSummary())

    <x-filament::section heading="O que guardamos sobre você">
        <div style="line-height: 1.7">
            <p>Nesta carteira estão armazenados <strong>{{ $summary['ativos'] }} ativos</strong> e <strong>{{ $summary['movimentacoes'] }} movimentações</strong>, além do seu nome e e-mail de cadastro. Esses dados são usados exclusivamente para exibir a sua carteira — nunca são vendidos ou compartilhados com terceiros.</p>
            <p style="margin-top: .75rem">As cotações e índices econômicos exibidos vêm de fontes públicas da internet e não têm vínculo com a sua identidade.</p>
        </div>
    </x-filament::section>

    <x-filament::section heading="Seus direitos (LGPD)">
        <ul style="line-height: 2; list-style: disc; padding-left: 1.25rem">
            <li><strong>Portabilidade:</strong> use "Exportar meus dados" acima para baixar tudo em JSON.</li>
            <li><strong>Correção:</strong> edite seus dados cadastrais no menu do seu perfil.</li>
            <li><strong>Exclusão:</strong> "Excluir minha conta" apaga definitivamente todos os seus dados.</li>
        </ul>
        <p style="margin-top: .75rem">
            Detalhes completos na
            <a href="{{ route('legal.privacidade') }}" target="_blank" style="text-decoration: underline">Política de Privacidade</a>.
        </p>
    </x-filament::section>

    <x-filament::section heading="Aviso sobre investimentos">
        <p style="line-height: 1.7">O Milia Invest é uma ferramenta de organização e acompanhamento. As informações exibidas <strong>não constituem recomendação de investimento</strong> nos termos da regulamentação da CVM — decisões de investimento são de sua exclusiva responsabilidade.</p>
    </x-filament::section>
</x-filament-panels::page>
