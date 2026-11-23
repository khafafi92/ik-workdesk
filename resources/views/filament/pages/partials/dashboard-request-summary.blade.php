<section class="ik-panel ik-request-summary" aria-labelledby="{{ $headingId }}">
    <div class="ik-panel-header">
        <h2 id="{{ $headingId }}">{{ $summary['title'] }}</h2>
        <a href="{{ $summary['url'] }}">{{ $summary['link'] }}</a>
    </div>

    @if ($summary['rows']->isEmpty())
        <p class="ik-request-summary-empty">{{ $summary['empty'] }}</p>
    @else
        <ul role="list" class="ik-request-summary-list">
            @foreach ($summary['rows'] as $row)
                <li>
                    <div>
                        @if ($row['url'])
                            <a href="{{ $row['url'] }}" class="ik-record-link">{{ $row['number'] }}</a>
                        @else
                            <strong>{{ $row['number'] }}</strong>
                        @endif
                        @if (filled($row['detail']))
                            <p>{{ $row['detail'] }}</p>
                        @endif
                    </div>
                    <div class="ik-request-summary-progress">
                        @if ($row['date'])
                            <p>{{ $row['date'] }}</p>
                        @endif
                        <strong>{{ $row['status'] }}</strong>
                        @if (filled($row['action'] ?? null))
                            <p>{{ $row['action'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
