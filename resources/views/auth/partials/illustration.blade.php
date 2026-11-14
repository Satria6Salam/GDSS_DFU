{{-- Ilustrasi SVG buatan sendiri (Bab 10.2): pola heksagon, lingkaran gradasi, kartu peringkat. --}}
<svg viewBox="0 0 520 430" class="w-100 h-auto" role="img" aria-label="Ilustrasi peringkat prioritas kasus">
    <defs>
        <linearGradient id="gdssGrad" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#1557D0"/><stop offset="1" stop-color="#0EA5A0"/>
        </linearGradient>
        <pattern id="hex" width="26" height="45" patternUnits="userSpaceOnUse">
            <path d="M13 0 L25 7 L25 22 L13 29 L1 22 L1 7 Z M13 29 L13 45" fill="none" stroke="#D6E4F7" stroke-width="1.2"/>
        </pattern>
    </defs>
    <rect x="0" y="0" width="520" height="430" fill="url(#hex)" opacity=".75"/>
    <circle cx="285" cy="215" r="175" fill="url(#gdssGrad)" opacity=".95"/>
    <circle cx="285" cy="215" r="135" fill="#fff" opacity=".1"/>
    <rect x="175" y="100" width="220" height="250" rx="22" fill="#fff"/>
    <rect x="198" y="124" width="110" height="12" rx="6" fill="#0B2E59"/>
    <rect x="198" y="144" width="70" height="8" rx="4" fill="#B8C7DC"/>
    <g font-family="Plus Jakarta Sans, Segoe UI, sans-serif" font-size="12" font-weight="700" fill="#0B2E59">
        @foreach ([['P06', 96], ['P04', 73], ['P07', 68], ['P08', 64]] as $i => [$code, $width])
            @php $y = 184 + 38 * $i; @endphp
            <circle cx="212" cy="{{ $y }}" r="13" fill="{{ $i === 0 ? 'url(#gdssGrad)' : '#EEF3FA' }}"/>
            <text x="208" y="{{ $y + 4 }}" fill="{{ $i === 0 ? '#fff' : '#0B2E59' }}">{{ $i + 1 }}</text>
            <text x="234" y="{{ $y + 4 }}">{{ $code }}</text>
            <rect x="270" y="{{ $y - 5 }}" width="100" height="10" rx="5" fill="#E7F0FD"/>
            <rect x="270" y="{{ $y - 5 }}" width="{{ $width }}" height="10" rx="5" fill="url(#gdssGrad)"/>
        @endforeach
    </g>
    <rect x="355" y="70" width="64" height="64" rx="18" fill="#fff"/>
    <path d="M387 88v28M373 102h28" stroke="url(#gdssGrad)" stroke-width="7" stroke-linecap="round"/>
    <circle cx="140" cy="330" r="30" fill="#fff"/>
    <path d="M128 330l8 8 16-16" stroke="#0EA5A0" stroke-width="5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
