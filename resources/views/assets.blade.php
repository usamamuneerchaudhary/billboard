@if ($stylesheet)
<link rel="stylesheet" href="{{ $stylesheet }}">
@endif
<script
    src="{{ $script }}"
    defer
    data-billboard-endpoint="{{ $endpoint }}"
    data-billboard-tracking="{{ $tracking ? '1' : '0' }}"
    data-billboard-privacy="{{ $respect_privacy ? '1' : '0' }}"
    data-billboard-breakpoint="{{ $breakpoint }}"
></script>
