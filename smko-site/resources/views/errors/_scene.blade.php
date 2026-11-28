{{-- Small illustration for 403 / 419 / 500 / 503: a glowing purple badge with an icon, among stars. --}}
<svg viewBox="0 0 320 256" class="h-full w-full overflow-visible">
    <defs>
        <linearGradient id="badge-{{ $icon }}" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#a78bfa"/><stop offset="1" stop-color="#6d28d9"/>
        </linearGradient>
        <radialGradient id="halo-{{ $icon }}"><stop offset="0" stop-color="#c4b5fd" stop-opacity=".55"/><stop offset="1" stop-color="#c4b5fd" stop-opacity="0"/></radialGradient>
    </defs>
    <g fill="#fbbf24">
        <path class="smko-twinkle" style="--d:0s" d="M52 50l3 8 8 3-8 3-3 8-3-8-8-3 8-3z"/>
        <path class="smko-twinkle" style="--d:1.2s" d="M272 66l2 6 6 2-6 2-2 6-2-6-6-2 6-2z"/>
        <path class="smko-twinkle" style="--d:2s" d="M262 196l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/>
        <circle class="smko-twinkle" style="--d:.6s" cx="88" cy="200" r="2.5"/>
        <circle class="smko-twinkle" style="--d:1.7s" cx="230" cy="24" r="2"/>
    </g>
    <circle cx="160" cy="124" r="104" fill="url(#halo-{{ $icon }})"/>
    <g class="smko-drift" style="transform-origin:160px 124px">
        <ellipse cx="160" cy="236" rx="56" ry="7" fill="#7c3aed" opacity=".12"/>
        <rect x="100" y="64" width="120" height="120" rx="32" fill="url(#badge-{{ $icon }})"/>
        <rect x="100" y="64" width="120" height="34" rx="17" fill="#fff" opacity=".12"/>
        <g fill="none" stroke="#fff" stroke-width="9" stroke-linecap="round" stroke-linejoin="round">
            @switch($icon)
                @case('lock')
                    <rect x="134" y="118" width="52" height="40" rx="8" fill="#fff" stroke="none"/>
                    <path d="M142 118v-12a18 18 0 0 1 36 0v12"/>
                    <circle cx="160" cy="138" r="5" fill="#7c3aed" stroke="none"/>
                    @break
                @case('hourglass')
                    <path d="M136 92h48M136 156h48M140 92c0 22 40 22 40 32s-40 10-40 32M180 92c0 22-40 22-40 32s40 10 40 32"/>
                    @break
                @case('spark')
                    <path d="M160 94v26M160 146v2M137 106l12 12M171 136l12 12M183 106l-12 12M149 136l-12 12"/>
                    @break
                @case('tools')
                    <path d="M138 150l26-26M168 96a16 16 0 0 0 16 22l-8 8a16 16 0 0 0-22-16z"/>
                    <path d="M138 104l26 26 8-8-26-26-8 8z" fill="#fff" stroke="none" opacity=".9"/>
                    @break
            @endswitch
        </g>
    </g>
</svg>
