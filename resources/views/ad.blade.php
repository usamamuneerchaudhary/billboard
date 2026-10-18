{{-- A single ad. Keep {!! $ad['attributes'] !!} on the outer element. --}}
<div class="billboard-ad billboard-ad--{{ $ad['type'] }}" {!! $ad['attributes'] !!}>
    @switch($ad['type'])
        @case('image')
            <a class="billboard-ad__link" href="{{ $ad['url'] }}" rel="{{ $ad['rel'] }}" @if ($ad['target']) target="_blank" @endif>
                <img
                    class="billboard-ad__image"
                    src="{{ $ad['image']['src'] }}"
                    alt="{{ $ad['image']['alt'] }}"
                    @if ($ad['image']['width']) width="{{ $ad['image']['width'] }}" @endif
                    @if ($ad['image']['height']) height="{{ $ad['image']['height'] }}" @endif
                    loading="lazy"
                    decoding="async"
                >
            </a>
            @break

        @case('text')
            <a class="billboard-ad__link" href="{{ $ad['url'] }}" rel="{{ $ad['rel'] }}" @if ($ad['target']) target="_blank" @endif>
                <span class="billboard-ad__headline">{{ $ad['headline'] }}</span>
                @if ($ad['body'] !== '')
                    <span class="billboard-ad__body">{{ $ad['body'] }}</span>
                @endif
                @if ($ad['button_text'] !== '')
                    <span class="billboard-ad__button">{{ $ad['button_text'] }}</span>
                @endif
            </a>
            @break

        @case('html')
            {!! $ad['html'] !!}
            @break
    @endswitch
</div>
