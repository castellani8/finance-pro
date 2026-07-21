<x-filament-widgets::widget>
    <x-filament::section heading="Comece por aqui 👋">
        <div style="line-height: 1.8">
            <p>Em menos de um minuto você vê toda a sua carteira consolidada:</p>
            <ol style="list-style: decimal; padding-left: 1.5rem; margin-top: .5rem">
                <li>
                    Acesse a <a href="https://www.investidor.b3.com.br" target="_blank" style="text-decoration: underline">área do investidor da B3</a>
                    e exporte o relatório <strong>Extratos &rarr; Movimentação</strong> (arquivo .xlsx).
                </li>
                <li>
                    Vá em <strong>Ativos</strong> e clique em <strong>Importar planilha B3</strong> — posições, proventos e rentabilidade aparecem na hora.
                </li>
                <li>
                    Volte aqui no painel para acompanhar a <strong>evolução do patrimônio</strong>, os <strong>proventos por mês</strong> e a <strong>alocação</strong>.
                </li>
            </ol>
            <div style="margin-top: 1rem">
                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Resources\Assets\AssetResource::getUrl() }}"
                    icon="heroicon-o-arrow-up-tray"
                >
                    Importar minha primeira planilha
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
