<x-filament-panels::page>
    <style>
        .fam-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .65rem 0; border-top: 1px solid rgba(128,128,128,.15); }
        .fam-row:first-child { border-top: 0; }
        .fam-name { font-weight: 600; font-size: .875rem; }
        .fam-sub { font-size: .75rem; opacity: .6; }
        .fam-remove { font-size: .75rem; color: #ef4444; background: none; border: 0; cursor: pointer; }
    </style>

    <x-filament::section heading="Quem participa desta carteira"
        description="Todos os membros veem e registram tudo — patrimônio, contas, lançamentos e relatórios.">
        @foreach ($this->getMembers() as $member)
            <div class="fam-row">
                <div>
                    <div class="fam-name">{{ $member->name }} @if ($member->id === auth()->id())<span class="fam-sub">(você)</span>@endif</div>
                    <div class="fam-sub">{{ $member->email }}</div>
                </div>
                @if ($member->id !== auth()->id())
                    <button class="fam-remove"
                            wire:click="removeMember({{ $member->id }})"
                            wire:confirm="Remover o acesso de {{ $member->name }} a esta carteira?">
                        Remover acesso
                    </button>
                @endif
            </div>
        @endforeach
    </x-filament::section>

    @if ($this->getPendingInvitations()->isNotEmpty())
        <x-filament::section heading="Convites pendentes">
            @foreach ($this->getPendingInvitations() as $invitation)
                <div class="fam-row">
                    <div>
                        <div class="fam-name">{{ $invitation->email }}</div>
                        <div class="fam-sub">
                            Convidado por {{ $invitation->inviter?->name ?? '—' }} ·
                            expira {{ $invitation->expires_at->locale('pt_BR')->diffForHumans() }}
                        </div>
                    </div>
                    <button class="fam-remove"
                            wire:click="revokeInvitation({{ $invitation->id }})"
                            wire:confirm="Revogar o convite para {{ $invitation->email }}?">
                        Revogar
                    </button>
                </div>
            @endforeach
        </x-filament::section>
    @endif

    <x-filament::section heading="Carta de patrimônio"
        description="Se algo acontecer com você, sua família saberia onde está cada ativo? A carta reúne contas, corretoras e bens num documento imprimível para guardar num lugar seguro (físico ou com o advogado da família).">
        <p style="font-size:.875rem; opacity:.7;">
            Use o botão "Carta de patrimônio" no topo da página para gerar a versão atualizada.
            Recomendamos reimprimir a cada 6 meses ou após mudanças grandes na carteira.
        </p>
    </x-filament::section>
</x-filament-panels::page>
