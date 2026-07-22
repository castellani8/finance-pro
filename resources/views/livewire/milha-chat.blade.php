<div
    x-data="{
        open: JSON.parse(localStorage.getItem('milha-open') ?? 'false'),
        toggle() { this.open = ! this.open; localStorage.setItem('milha-open', JSON.stringify(this.open)); this.$nextTick(() => this.scrollBottom()) },
        scrollBottom() { if (this.$refs.log) this.$refs.log.scrollTop = this.$refs.log.scrollHeight },
        scrollToStart(index) {
            this.$nextTick(() => {
                const el = this.$refs.log?.querySelector('[data-index=\'' + index + '\']')
                if (el) this.$refs.log.scrollTop = Math.max(0, el.offsetTop - this.$refs.log.offsetTop - 8)
            })
        },
    }"
    x-on:milha-scroll.window="$nextTick(() => scrollBottom())"
    x-on:milha-scroll-start.window="scrollToStart($event.detail.index)"
    x-init="$nextTick(() => scrollBottom())"
    class="milha-root"
>
    {{-- Painel do chat --}}
    <div x-show="open" x-cloak class="milha-panel" @if ($this->isAwaiting()) wire:poll.1500ms="pollReply" @endif>
        <div class="milha-header">
            <div class="milha-header-id">
                @php $milhaAvatar = file_exists(public_path('images/milha-avatar.jpg')) ? 'images/milha-avatar.jpg' : 'images/milha-avatar.svg'; @endphp
                <img class="milha-avatar" src="{{ asset($milhaAvatar) }}" alt="Milha">
                <span>
                    <strong>Milha</strong>
                    <small>sua parceira rumo à liberdade financeira</small>
                </span>
            </div>
            <div class="milha-header-actions">
                <button type="button" title="Nova conversa" wire:click="newConversation">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </button>
                <button type="button" title="Fechar" x-on:click="toggle()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <div class="milha-log" x-ref="log">
            @if ($messages === [])
                <div class="milha-msg milha-msg-assistant">
                    <p>Oi! Eu sou a <strong>Milha</strong> 💛 Posso te contar quanto você recebeu de
                    proventos, como está sua renda passiva, seus saldos… e até criar lançamentos
                    ou cadastrar ativos por você (sempre com a sua confirmação). Bora?</p>
                </div>
            @endif

            @foreach ($messages as $index => $message)
                @if ($message['role'] === 'chart')
                    <div class="milha-msg milha-msg-chart" data-index="{{ $index }}">{!! $message['html'] !!}</div>
                @else
                    <div class="milha-msg {{ $message['role'] === 'user' ? 'milha-msg-user' : 'milha-msg-assistant' }}" data-index="{{ $index }}">
                        {!! $message['html'] !!}
                    </div>
                @endif
            @endforeach

            @foreach ($pending as $approval)
                <div class="milha-approval" wire:key="approval-{{ $approval['id'] }}">
                    <p class="milha-approval-title">A Milha quer executar: <strong>{{ $approval['label'] }}</strong></p>
                    <dl>
                        @foreach ($approval['arguments'] as $key => $value)
                            <div>
                                <dt>{{ str_replace('_', ' ', $key) }}</dt>
                                <dd>{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <div class="milha-approval-actions">
                        <button type="button" class="milha-btn-approve" wire:click="approve" @disabled($this->isAwaiting())>Aprovar</button>
                        <button type="button" class="milha-btn-reject" wire:click="reject" @disabled($this->isAwaiting())>Recusar</button>
                    </div>
                </div>
            @endforeach

            @if ($this->isAwaiting())
                <div class="milha-msg milha-msg-assistant milha-typing" style="display:flex">
                    <span></span><span></span><span></span>
                </div>
            @endif
        </div>

        <form class="milha-input" wire:submit="send">
            <input
                type="text"
                wire:model="input"
                maxlength="2000"
                placeholder="{{ $pending !== [] ? 'Responda à confirmação acima…' : 'Pergunte sobre sua carteira…' }}"
                @disabled($pending !== [] || $this->isAwaiting())
                autocomplete="off"
            />
            @if ($this->isAwaiting())
                {{-- Janela de arrependimento: interrompe o turno enfileirado --}}
                <button type="button" class="milha-btn-stop" title="Parar" wire:click="stop">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><rect x="7" y="7" width="10" height="10" rx="1.5"/></svg>
                </button>
            @else
                <button type="submit" title="Enviar" @disabled($pending !== [])>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                </button>
            @endif
        </form>

        <p class="milha-disclaimer">A Milha não faz recomendação de investimento e pode cometer erros. Confira os números nas telas do painel.</p>
    </div>

    {{-- Balão flutuante --}}
    <button type="button" class="milha-bubble" x-on:click="toggle()" :aria-expanded="open" aria-label="Conversar com a Milha">
        <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/></svg>
        <svg x-show="open" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
    </button>

    <style>
        .milha-root { --milha-gold: #D4AF37; --milha-gold-dark: #a8862a; }
        .milha-root [x-cloak] { display: none !important; }

        .milha-bubble {
            position: fixed; bottom: 1.25rem; right: 1.25rem; z-index: 50;
            width: 3.5rem; height: 3.5rem; border-radius: 9999px; border: none; cursor: pointer;
            background: linear-gradient(135deg, var(--milha-gold), var(--milha-gold-dark));
            color: #100c02; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 25px rgb(0 0 0 / .35); transition: transform .15s ease;
        }
        .milha-bubble:hover { transform: scale(1.06); }
        .milha-bubble svg { width: 1.6rem; height: 1.6rem; }

        .milha-panel {
            position: fixed; bottom: 5.5rem; right: 1.25rem; z-index: 50;
            width: min(24rem, calc(100vw - 2rem)); height: min(36rem, calc(100dvh - 8rem));
            display: flex; flex-direction: column; overflow: hidden;
            border-radius: 1rem; background: #fff; color: #18181b;
            border: 1px solid rgb(212 175 55 / .35);
            box-shadow: 0 25px 60px rgb(0 0 0 / .45);
            font-size: .875rem;
        }
        .dark .milha-panel { background: #18181b; color: #e4e4e7; }

        .milha-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: .75rem 1rem; border-bottom: 1px solid rgb(212 175 55 / .3);
            background: linear-gradient(135deg, rgb(212 175 55 / .16), transparent);
        }
        .milha-header-id { display: flex; align-items: center; gap: .6rem; }
        .milha-header-id span:last-child { display: flex; flex-direction: column; line-height: 1.15; }
        .milha-header-id small { opacity: .65; font-size: .72rem; }
        .milha-avatar {
            width: 2.3rem; height: 2.3rem; border-radius: 9999px; flex-shrink: 0; display: block;
            object-fit: cover; border: 1px solid rgb(212 175 55 / .5);
        }
        .milha-header-actions { display: flex; gap: .25rem; }
        .milha-header-actions button {
            background: none; border: none; cursor: pointer; color: inherit; opacity: .6;
            padding: .35rem; border-radius: .5rem;
        }
        .milha-header-actions button:hover { opacity: 1; background: rgb(212 175 55 / .15); }
        .milha-header-actions svg { width: 1.1rem; height: 1.1rem; }

        .milha-log { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: .6rem; }
        .milha-msg { max-width: 85%; padding: .55rem .8rem; border-radius: .9rem; overflow-wrap: break-word; }
        .milha-msg :is(p, ul, ol) { margin: 0 0 .35rem; }
        .milha-msg :is(p, ul, ol):last-child { margin-bottom: 0; }
        .milha-msg ul, .milha-msg ol { padding-left: 1.1rem; }
        .milha-msg-user {
            align-self: flex-end; background: linear-gradient(135deg, var(--milha-gold), var(--milha-gold-dark));
            color: #100c02; border-bottom-right-radius: .25rem;
        }
        .milha-msg-assistant {
            align-self: flex-start; background: rgb(113 113 122 / .14);
            border-bottom-left-radius: .25rem;
        }
        .dark .milha-msg-assistant { background: rgb(63 63 70 / .55); }

        .milha-msg-chart {
            align-self: stretch; max-width: 100%; padding: .7rem .8rem;
            background: rgb(113 113 122 / .1); border: 1px solid rgb(212 175 55 / .3);
            --milha-chart-bg: #fff;
        }
        .dark .milha-msg-chart { background: rgb(63 63 70 / .4); --milha-chart-bg: #232326; }

        .milha-typing { gap: .3rem; align-items: center; padding: .8rem; }
        .milha-typing span {
            width: .42rem; height: .42rem; border-radius: 9999px; background: var(--milha-gold);
            animation: milha-blink 1.2s infinite;
        }
        .milha-typing span:nth-child(2) { animation-delay: .2s; }
        .milha-typing span:nth-child(3) { animation-delay: .4s; }
        @keyframes milha-blink { 0%, 80%, 100% { opacity: .25 } 40% { opacity: 1 } }

        .milha-approval {
            align-self: stretch; border: 1px solid rgb(212 175 55 / .5); border-radius: .9rem;
            padding: .75rem .9rem; background: rgb(212 175 55 / .08);
        }
        .milha-approval-title { margin: 0 0 .5rem; }
        .milha-approval dl { margin: 0 0 .6rem; display: grid; grid-template-columns: auto 1fr; gap: .15rem .6rem; }
        .milha-approval dl div { display: contents; }
        .milha-approval dt { opacity: .6; text-transform: capitalize; }
        .milha-approval dd { margin: 0; font-weight: 600; }
        .milha-approval-actions { display: flex; gap: .5rem; }
        .milha-approval-actions button {
            flex: 1; border: none; border-radius: .6rem; padding: .45rem 0; font-weight: 700; cursor: pointer;
        }
        .milha-btn-approve { background: linear-gradient(135deg, var(--milha-gold), var(--milha-gold-dark)); color: #100c02; }
        .milha-btn-reject { background: rgb(113 113 122 / .25); color: inherit; }
        .milha-approval-actions button:disabled { opacity: .5; cursor: wait; }

        .milha-input {
            display: flex; gap: .5rem; padding: .75rem; border-top: 1px solid rgb(212 175 55 / .3);
        }
        .milha-input input {
            flex: 1; border: 1px solid rgb(113 113 122 / .35); border-radius: .7rem;
            background: transparent; color: inherit; padding: .5rem .75rem; font-size: .875rem; outline: none;
        }
        .milha-input input:focus { border-color: var(--milha-gold); }
        .milha-input button {
            border: none; border-radius: .7rem; width: 2.6rem; cursor: pointer;
            background: linear-gradient(135deg, var(--milha-gold), var(--milha-gold-dark)); color: #100c02;
            display: flex; align-items: center; justify-content: center;
        }
        .milha-input button:disabled { opacity: .5; cursor: not-allowed; }
        .milha-input svg { width: 1.15rem; height: 1.15rem; }
        .milha-btn-stop { background: rgb(113 113 122 / .3) !important; color: inherit !important; }
        .milha-btn-stop:hover { background: rgb(220 38 38 / .25) !important; }

        .milha-disclaimer { margin: 0; padding: 0 .9rem .6rem; font-size: .68rem; opacity: .5; text-align: center; }
    </style>
</div>
