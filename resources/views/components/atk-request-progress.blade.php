@props(['request'])

<div class="ik-atk-progress-list">
    @forelse (\App\Filament\Resources\AtkRequests\AtkRequestResource::itemProgressList($request) as $item)
        <div class="ik-atk-progress-item">
            <p class="ik-atk-progress-name">{{ $item['name'] }}</p>
            <dl class="ik-atk-progress-metrics">
                @foreach ($item['metrics'] as $label => $quantity)
                    <div @class(['ik-atk-progress-metric', 'is-shortage' => $label === 'Kekurangan' && $item['shortage'] > 0])>
                        <dt>{{ $label }}:</dt>
                        <dd>{{ rtrim(rtrim(number_format($quantity, 2, ',', '.'), '0'), ',') }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @empty
        <p class="ik-atk-progress-empty">Belum ada barang dalam permintaan ini.</p>
    @endforelse
</div>
