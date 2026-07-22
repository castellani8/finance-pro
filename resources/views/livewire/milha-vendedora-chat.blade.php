<div
    x-data="{
        open: false,
        isMobile: window.matchMedia('(max-width: 640px)').matches,
        shake: false,
        init() {
            // Desktop: abre sozinho na primeira visita. Mobile: nunca abre
            // sozinho — o balão chacoalha até o primeiro clique.
            if (this.isMobile) {
                if (! localStorage.getItem('milha-lp-noticed')) this.shake = true
            } else if (! localStorage.getItem('milha-lp-dismissed')) {
                setTimeout(() => { this.open = true; this.$nextTick(() => this.scrollBottom()) }, 1500)
            }
        },
        toggle() {
            this.shake = false
            localStorage.setItem('milha-lp-noticed', '1')
            this.open = ! this.open
            if (! this.open) localStorage.setItem('milha-lp-dismissed', '1')
            this.$nextTick(() => this.scrollBottom())
        },
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
    class="milha-lp-root"
>
    {{-- Fundo com blur (só no mobile): tira a poluição da landing atrás do chat --}}
    <div x-show="open" x-cloak class="milha-lp-overlay" x-on:click="toggle()" aria-hidden="true"></div>

    {{-- Painel do chat --}}
    <div x-show="open" x-cloak class="milha-lp-panel" @if ($this->isAwaiting()) wire:poll.1500ms="pollReply" @endif>
        <div class="milha-lp-header">
            <div class="milha-lp-header-id">
                @php $milhaAvatar = file_exists(public_path('images/milha-avatar.jpg')) ? 'images/milha-avatar.jpg' : 'images/milha-avatar.svg'; @endphp
                <img class="milha-lp-avatar" src="{{ asset($milhaAvatar) }}" alt="Milha">
                <span>
                    <strong>Milha</strong>
                    <small>tire suas dúvidas — resposta na hora</small>
                </span>
            </div>
            <button type="button" title="Fechar" x-on:click="toggle()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Cliques em links de cadastro dentro da conversa contam como CTA --}}
        <div class="milha-lp-log" x-ref="log"
            x-on:click="const a = $event.target.closest('a'); if (a && a.href.includes('/app/register')) { $event.preventDefault(); $wire.ctaClick() }">
            @foreach ($messages as $index => $message)
                <div class="milha-lp-msg {{ $message['role'] === 'user' ? 'milha-lp-msg-user' : 'milha-lp-msg-assistant' }}" data-index="{{ $index }}">
                    {!! $message['html'] !!}
                </div>
            @endforeach

            @if ($this->isAwaiting())
                <div class="milha-lp-msg milha-lp-msg-assistant milha-lp-typing" style="display:flex">
                    <span></span><span></span><span></span>
                </div>
            @endif
        </div>

        <form class="milha-lp-input" wire:submit="send">
            <input
                type="text"
                wire:model="input"
                maxlength="500"
                placeholder="Pergunte qualquer coisa sobre o Milia…"
                @disabled($this->isAwaiting())
                autocomplete="off"
            />
            @if ($this->isAwaiting())
                <button type="button" class="milha-lp-btn-stop" title="Parar" wire:click="stop">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><rect x="7" y="7" width="10" height="10" rx="1.5"/></svg>
                </button>
            @else
                <button type="submit" title="Enviar">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                </button>
            @endif
        </form>

        <a href="{{ url('/app/register') }}" class="milha-lp-cta" x-on:click.prevent="$wire.ctaClick()">
            Começar meus {{ config('landing.plan.trial_days') }} dias grátis →
        </a>
    </div>

    {{-- Balão flutuante (some no mobile enquanto o chat está aberto) --}}
    <button type="button" class="milha-lp-bubble" x-show="!(open && isMobile)" :class="{ 'milha-lp-shake': shake }" x-on:click="toggle()" :aria-expanded="open" aria-label="Conversar com a Milha">
        <span x-show="!open" class="milha-lp-badge" x-cloak>1</span>
        <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
        <svg x-show="open" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
    </button>

    <style>
        .milha-lp-root { --milha-gold: #D4AF37; --milha-gold-dark: #a8862a; }
        .milha-lp-root [x-cloak] { display: none !important; }

        .milha-lp-bubble {
            position: fixed; bottom: 1.25rem; right: 1.25rem; z-index: 50;
            width: 3.5rem; height: 3.5rem; border-radius: 9999px; border: none; cursor: pointer;
            background: linear-gradient(135deg, var(--milha-gold), var(--milha-gold-dark));
            color: #100c02; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 25px rgb(0 0 0 / .5); transition: transform .15s ease;
        }
        .milha-lp-bubble:hover { transform: scale(1.06); }
        .milha-lp-bubble svg { width: 1.6rem; height: 1.6rem; }
        .milha-lp-badge {
            position: absolute; top: -2px; right: -2px; width: 1.2rem; height: 1.2rem;
            border-radius: 9999px; background: #dc2626; color: #fff; font-size: .7rem;
            font-weight: 700; display: flex; align-items: center; justify-content: center;
        }

        .milha-lp-panel {
            position: fixed; bottom: 5.5rem; right: 1.25rem; z-index: 50;
            width: min(24rem, calc(100vw - 2rem)); height: min(34rem, calc(100dvh - 8rem));
            display: flex; flex-direction: column; overflow: hidden;
            border-radius: 1rem; background: #131313; color: #e4e4e7;
            border: 1px solid rgb(212 175 55 / .35);
            box-shadow: 0 25px 60px rgb(0 0 0 / .6);
            font-size: .875rem; text-align: left;
        }

        .milha-lp-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: .75rem 1rem; border-bottom: 1px solid rgb(212 175 55 / .3);
            background: linear-gradient(135deg, rgb(212 175 55 / .16), transparent);
        }
        .milha-lp-header-id { display: flex; align-items: center; gap: .6rem; }
        .milha-lp-header-id span:last-child { display: flex; flex-direction: column; line-height: 1.15; }
        .milha-lp-header-id small { opacity: .65; font-size: .72rem; }
        .milha-lp-avatar {
            width: 2.3rem; height: 2.3rem; border-radius: 9999px; flex-shrink: 0; display: block;
            object-fit: cover; border: 1px solid rgb(212 175 55 / .5);
        }
        .milha-lp-header > button {
            background: none; border: none; cursor: pointer; color: inherit; opacity: .6;
            padding: .35rem; border-radius: .5rem;
        }
        .milha-lp-header > button:hover { opacity: 1; background: rgb(212 175 55 / .15); }
        .milha-lp-header svg { width: 1.1rem; height: 1.1rem; }

        .milha-lp-log { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: .6rem; }
        .milha-lp-msg { max-width: 88%; padding: .55rem .8rem; border-radius: .9rem; overflow-wrap: break-word; line-height: 1.45; }
        .milha-lp-msg :is(p, ul, ol) { margin: 0 0 .35rem; }
        .milha-lp-msg :is(p, ul, ol):last-child { margin-bottom: 0; }
        .milha-lp-msg ul, .milha-lp-msg ol { padding-left: 1.1rem; }
        .milha-lp-msg a { color: var(--milha-gold); font-weight: 700; text-decoration: underline; }
        .milha-lp-msg-user {
            align-self: flex-end; background: linear-gradient(135deg, var(--milha-gold), var(--milha-gold-dark));
            color: #100c02; border-bottom-right-radius: .25rem;
        }
        .milha-lp-msg-assistant {
            align-self: flex-start; background: rgb(63 63 70 / .55);
            border-bottom-left-radius: .25rem;
        }

        .milha-lp-typing { gap: .3rem; align-items: center; padding: .8rem; }
        .milha-lp-typing span {
            width: .42rem; height: .42rem; border-radius: 9999px; background: var(--milha-gold);
            animation: milha-lp-blink 1.2s infinite;
        }
        .milha-lp-typing span:nth-child(2) { animation-delay: .2s; }
        .milha-lp-typing span:nth-child(3) { animation-delay: .4s; }
        @keyframes milha-lp-blink { 0%, 80%, 100% { opacity: .25 } 40% { opacity: 1 } }

        .milha-lp-input {
            display: flex; gap: .5rem; padding: .75rem .75rem .5rem; border-top: 1px solid rgb(212 175 55 / .3);
        }
        .milha-lp-input input {
            flex: 1; border: 1px solid rgb(113 113 122 / .35); border-radius: .7rem;
            background: transparent; color: inherit; padding: .5rem .75rem; font-size: .875rem; outline: none;
        }
        .milha-lp-input input:focus { border-color: var(--milha-gold); }
        .milha-lp-input button {
            border: none; border-radius: .7rem; width: 2.6rem; cursor: pointer;
            background: linear-gradient(135deg, var(--milha-gold), var(--milha-gold-dark)); color: #100c02;
            display: flex; align-items: center; justify-content: center;
        }
        .milha-lp-input button:disabled { opacity: .5; cursor: not-allowed; }
        .milha-lp-input svg { width: 1.15rem; height: 1.15rem; }
        .milha-lp-btn-stop { background: rgb(113 113 122 / .3) !important; color: #e4e4e7 !important; }
        .milha-lp-btn-stop:hover { background: rgb(220 38 38 / .25) !important; }

        .milha-lp-cta {
            display: block; margin: .25rem .75rem .75rem; padding: .6rem 0; text-align: center;
            border-radius: .7rem; background: linear-gradient(135deg, var(--milha-gold), var(--milha-gold-dark));
            color: #100c02; font-weight: 800; text-decoration: none; font-size: .875rem;
            transition: transform .12s ease;
        }
        .milha-lp-cta:hover { transform: scale(1.02); }

        /* Balão chacoalhando (mobile): rajada curta de wiggle, pausa e repete */
        @keyframes milha-lp-wiggle {
            0%, 14%, 100% { transform: rotate(0); }
            2% { transform: rotate(-12deg) scale(1.06); }
            5% { transform: rotate(10deg) scale(1.06); }
            8% { transform: rotate(-8deg); }
            11% { transform: rotate(6deg); }
        }
        .milha-lp-shake { animation: milha-lp-wiggle 3s ease-in-out infinite; }

        /* Overlay com blur — só existe no mobile */
        .milha-lp-overlay { display: none; }

        @media (max-width: 640px) {
            .milha-lp-overlay {
                display: block; position: fixed; inset: 0; z-index: 49;
                background: rgb(0 0 0 / .55);
                -webkit-backdrop-filter: blur(5px); backdrop-filter: blur(5px);
            }
            /* Painel centralizado de verdade, mais respiro, altura contida */
            .milha-lp-panel {
                left: .75rem; right: .75rem; width: auto;
                bottom: .75rem; height: min(74dvh, 32rem);
            }
        }
    </style>
</div>
