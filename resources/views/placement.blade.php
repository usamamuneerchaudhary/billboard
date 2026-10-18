{{--
    Billboard placement wrapper.

    Publish with `php artisan vendor:publish --tag=billboard-views` to customise.
    Keep the data-billboard-* attributes: the script uses them for rotation,
    device targeting and impression counting.
--}}
<aside
    class="billboard billboard--{{ $handle }}"
    data-billboard-placement="{{ $handle }}"
    data-billboard-mode="{{ $mode }}"
    data-billboard-rotation="{{ $rotation }}"
    data-billboard-max="{{ $max }}"
    aria-label="{{ $aria_label }}"
    @if ($style) style="{{ $style }}" @endif
>
    @if ($label)
        <div class="billboard__label">{{ $label }}</div>
    @endif

    @if ($mode === 'client')
        @foreach ($ads as $ad)
            <template data-billboard-candidate>@include('billboard::ad', ['ad' => $ad])</template>
        @endforeach
        @if ($fallback)
            <template data-billboard-fallback>{!! $fallback !!}</template>
        @endif
        <div class="billboard__slot" data-billboard-slot></div>
    @else
        <div class="billboard__slot" data-billboard-slot>
            @forelse ($ads as $ad)
                @include('billboard::ad', ['ad' => $ad])
            @empty
                <div class="billboard__fallback">{!! $fallback !!}</div>
            @endforelse
        </div>
        @if ($fallback && count($ads))
            <template data-billboard-fallback>{!! $fallback !!}</template>
        @endif
    @endif
</aside>
