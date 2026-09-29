@php
    $humanize = fn ($key) => ucwords(str_replace(['_', '-'], ' ', (string) $key));
@endphp

@if (is_array($summary) && $summary !== [])
    <table class="table table-sm table-borderless mb-0">
        <tbody>
            @foreach ($summary as $key => $value)
                <tr>
                    <th class="text-muted small fw-normal" style="width: 45%;">{{ $humanize($key) }}</th>
                    <td>
                        @if (is_array($value))
                            @if (array_is_list($value))
                                {{ $value === [] ? '—' : implode(', ', array_map(fn ($item) => is_scalar($item) || $item === null ? (string) $item : json_encode($item), $value)) }}
                            @else
                                @include('analytics.partials.report-summary', ['summary' => $value])
                            @endif
                        @elseif (is_bool($value))
                            {{ $value ? 'Yes' : 'No' }}
                        @elseif ($value === null || $value === '')
                            <span class="text-muted">—</span>
                        @else
                            {{ $value }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="text-muted mb-0">No summary data recorded for this report.</p>
@endif
