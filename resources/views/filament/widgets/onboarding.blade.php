<x-filament-widgets::widget>
    <div @class(['mi-onb' => ! $this->dismissed])>
        @unless ($this->dismissed)
            {{-- Cabeçalho: título + progresso + dispensar --}}
            <div class="mi-onb-header">
                <div class="mi-onb-heading">
                    <div class="mi-onb-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 19 V7 L12 14 L19 7 V19"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="mi-onb-title">
                            {{ $allDone ? 'Sua carteira está 100% configurada 🎉' : 'Primeiros passos na Milia Invest' }}
                        </h2>
                        <p class="mi-onb-subtitle">
                            {{ $allDone
                                ? 'A partir de agora o painel trabalha sozinho: cotações, alertas e fotos diárias do patrimônio.'
                                : 'Complete as etapas abaixo e deixe o painel trabalhando por você — leva poucos minutos.' }}
                        </p>
                    </div>
                </div>

                <div class="mi-onb-progress-wrap">
                    <div class="mi-onb-progress-label">
                        <span class="mi-onb-progress-count">{{ $completed }} de {{ $total }}</span> concluídos
                    </div>
                    <div class="mi-onb-progress" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="mi-onb-progress-fill" style="width: {{ $percent }}%"></div>
                    </div>
                </div>

                <button
                    type="button"
                    class="mi-onb-close"
                    title="Ocultar guia"
                    aria-label="Ocultar guia de primeiros passos"
                    wire:click="dismiss"
                    @unless ($allDone) wire:confirm="Ocultar o guia de primeiros passos? Você pode continuar configurando tudo normalmente pelo menu." @endunless
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>
            </div>

            {{-- Etapas --}}
            <div class="mi-onb-grid">
                @foreach ($steps as $index => $step)
                    <div @class(['mi-onb-step', 'is-done' => $step['done']])>
                        <div class="mi-onb-step-head">
                            <span @class(['mi-onb-badge', 'is-done' => $step['done']]) aria-hidden="true">
                                @if ($step['done'])
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 13l4 4L19 7"/>
                                    </svg>
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </span>
                            <h3 class="mi-onb-step-title">{{ $step['title'] }}</h3>
                        </div>

                        <p class="mi-onb-step-desc">{{ $step['description'] }}</p>

                        @if ($step['done'])
                            <span class="mi-onb-step-status">Concluído</span>
                        @elseif ($step['url'])
                            <a href="{{ $step['url'] }}" class="mi-onb-step-cta">
                                {{ $step['cta'] }}
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M5 12h14m-6-6 6 6-6 6"/>
                                </svg>
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Conclusão --}}
            @if ($allDone)
                <div class="mi-onb-done">
                    <p>Tudo pronto. Bons investimentos!</p>
                    <button type="button" class="mi-onb-done-button" wire:click="dismiss">
                        Concluir guia
                    </button>
                </div>
            @endif

        <style>
            .mi-onb {
                position: relative;
                border-radius: 0.75rem;
                padding: 1.5rem;
                background:
                    radial-gradient(60rem 18rem at 85% -30%, rgb(212 175 55 / 0.09), transparent 60%),
                    #fff;
                border: 1px solid rgb(212 175 55 / 0.35);
                box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
            }
            .dark .mi-onb {
                background:
                    radial-gradient(60rem 18rem at 85% -30%, rgb(212 175 55 / 0.10), transparent 60%),
                    #101012;
                border-color: rgb(212 175 55 / 0.30);
            }

            .mi-onb-header {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-start;
                gap: 1rem 1.5rem;
                padding-right: 2.25rem;
            }
            .mi-onb-heading {
                display: flex;
                align-items: flex-start;
                gap: 0.875rem;
                flex: 1 1 20rem;
            }
            .mi-onb-mark {
                flex-shrink: 0;
                display: grid;
                place-items: center;
                width: 2.75rem;
                height: 2.75rem;
                border-radius: 0.75rem;
                background: linear-gradient(135deg, #1d1d1d, #000);
                border: 1px solid rgb(212 175 55 / 0.55);
                color: #d4af37;
            }
            .mi-onb-mark svg { width: 1.375rem; height: 1.375rem; }
            .mi-onb-title {
                font-size: 1.0625rem;
                font-weight: 700;
                color: #111827;
                line-height: 1.4;
            }
            .dark .mi-onb-title { color: #fafaf9; }
            .mi-onb-subtitle {
                margin-top: 0.125rem;
                font-size: 0.875rem;
                color: #6b7280;
                line-height: 1.5;
            }
            .dark .mi-onb-subtitle { color: #a1a1aa; }

            .mi-onb-progress-wrap { flex: 0 1 14rem; min-width: 11rem; }
            .mi-onb-progress-label {
                font-size: 0.75rem;
                color: #6b7280;
                margin-bottom: 0.375rem;
                text-align: right;
            }
            .dark .mi-onb-progress-label { color: #a1a1aa; }
            .mi-onb-progress-count { font-weight: 700; color: #b18f27; }
            .dark .mi-onb-progress-count { color: #e2c55c; }
            .mi-onb-progress {
                height: 0.5rem;
                border-radius: 9999px;
                background: rgb(212 175 55 / 0.15);
                overflow: hidden;
            }
            .mi-onb-progress-fill {
                height: 100%;
                border-radius: 9999px;
                background: linear-gradient(90deg, #b18f27, #d4af37 60%, #f0d882);
                transition: width 300ms ease;
            }

            .mi-onb-close {
                position: absolute;
                top: 1rem;
                right: 1rem;
                display: grid;
                place-items: center;
                width: 1.75rem;
                height: 1.75rem;
                border-radius: 9999px;
                color: #9ca3af;
                transition: color 150ms ease, background 150ms ease;
            }
            .mi-onb-close:hover { color: #4b5563; background: rgb(0 0 0 / 0.05); }
            .dark .mi-onb-close { color: #71717a; }
            .dark .mi-onb-close:hover { color: #d4d4d8; background: rgb(255 255 255 / 0.06); }
            .mi-onb-close svg { width: 1rem; height: 1rem; }

            .mi-onb-grid {
                margin-top: 1.25rem;
                display: grid;
                gap: 0.75rem;
                grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
            }
            .mi-onb-step {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                padding: 1rem;
                border-radius: 0.625rem;
                border: 1px solid rgb(0 0 0 / 0.08);
                background: rgb(0 0 0 / 0.015);
                transition: border-color 150ms ease;
            }
            .mi-onb-step:hover { border-color: rgb(212 175 55 / 0.45); }
            .dark .mi-onb-step {
                border-color: rgb(255 255 255 / 0.08);
                background: rgb(255 255 255 / 0.02);
            }
            .mi-onb-step.is-done { opacity: 0.72; }

            .mi-onb-step-head { display: flex; align-items: center; gap: 0.625rem; }
            .mi-onb-badge {
                flex-shrink: 0;
                display: grid;
                place-items: center;
                width: 1.625rem;
                height: 1.625rem;
                border-radius: 9999px;
                font-size: 0.8125rem;
                font-weight: 700;
                color: #b18f27;
                border: 1.5px solid rgb(212 175 55 / 0.6);
            }
            .dark .mi-onb-badge { color: #e2c55c; }
            .mi-onb-badge.is-done {
                color: #0a0a0a;
                border-color: transparent;
                background: linear-gradient(135deg, #e2c55c, #d4af37);
            }
            .mi-onb-badge svg { width: 0.875rem; height: 0.875rem; }

            .mi-onb-step-title {
                font-size: 0.9375rem;
                font-weight: 600;
                color: #1f2937;
            }
            .dark .mi-onb-step-title { color: #f4f4f5; }
            .mi-onb-step.is-done .mi-onb-step-title { text-decoration: line-through; text-decoration-color: rgb(212 175 55 / 0.6); }

            .mi-onb-step-desc {
                font-size: 0.8125rem;
                line-height: 1.55;
                color: #6b7280;
                flex-grow: 1;
            }
            .dark .mi-onb-step-desc { color: #a1a1aa; }

            .mi-onb-step-status {
                font-size: 0.75rem;
                font-weight: 600;
                color: #b18f27;
            }
            .dark .mi-onb-step-status { color: #e2c55c; }

            .mi-onb-step-cta {
                display: inline-flex;
                align-items: center;
                gap: 0.375rem;
                font-size: 0.8125rem;
                font-weight: 600;
                color: #b18f27;
                transition: color 150ms ease, gap 150ms ease;
            }
            .mi-onb-step-cta:hover { color: #8c6f1d; gap: 0.625rem; }
            .dark .mi-onb-step-cta { color: #e2c55c; }
            .dark .mi-onb-step-cta:hover { color: #f0d882; }
            .mi-onb-step-cta svg { width: 0.875rem; height: 0.875rem; }

            .mi-onb-done {
                margin-top: 1.25rem;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                padding: 1rem 1.25rem;
                border-radius: 0.625rem;
                border: 1px solid rgb(212 175 55 / 0.4);
                background: rgb(212 175 55 / 0.08);
                font-size: 0.875rem;
                font-weight: 600;
                color: #1f2937;
            }
            .dark .mi-onb-done { color: #f4f4f5; }
            .mi-onb-done-button {
                padding: 0.5rem 1.25rem;
                border-radius: 9999px;
                font-size: 0.8125rem;
                font-weight: 700;
                color: #0a0a0a;
                background: linear-gradient(135deg, #e2c55c, #d4af37);
                transition: filter 150ms ease;
            }
            .mi-onb-done-button:hover { filter: brightness(1.06); }
        </style>
        @endunless
    </div>
</x-filament-widgets::widget>
