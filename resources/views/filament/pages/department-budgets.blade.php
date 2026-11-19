<x-filament-panels::page>
@vite('resources/css/scm.css')
<div class="scm-workspace">
    @if($missing)
        <section class="scm-panel"><p role="alert">Data anggaran belum disiapkan. Hubungi administrator.</p></section>
    @else
        <section class="scm-panel"><h2>Anggaran departemen</h2>
            <p>Alokasi anggaran untuk departemen yang dapat Anda akses, dipisahkan berdasarkan perusahaan, tahun, HO/project, COA, dan mata uang.</p>
            <p role="status">Saldo memperhitungkan PO yang sudah dibuat dan pengajuan SPD. Pembayaran PO mengubah komitmen menjadi realisasi. LPJ yang disetujui mengganti komitmen SPD dengan biaya aktual.</p>
            <div class="scm-scroll" role="region" aria-label="Daftar anggaran departemen" tabindex="0"><table><thead><tr><th scope="col">Perusahaan / tahun</th><th scope="col">HO / project</th><th scope="col">Departemen</th><th scope="col">COA</th><th scope="col">Alokasi</th><th scope="col">Realisasi</th><th scope="col">Komitmen</th><th scope="col">Sisa</th></tr></thead><tbody>
                @forelse($lines as $line)@php($usage = $line->budget_usage)<tr wire:key="department-budget-{{ $line->id }}"><td>{{ $line->scope->budget->company }} / {{ $line->scope->budget->year }}</td><td>{{ $line->scope->need_type === 'HO' ? 'HO' : ($line->scope->project?->code.' · '.$line->scope->project?->name) }}</td><td>{{ $line->department->name }}</td><td>{{ $line->account->code }} · {{ $line->account->name }}</td><td>{{ \App\Services\Scm\Decimal::money($line->amount_minor, $line->scope->budget->currency) }}</td><td>{{ \App\Services\Scm\Decimal::money($usage['realized_minor'], $line->scope->budget->currency) }}</td><td>{{ \App\Services\Scm\Decimal::money($usage['committed_minor'], $line->scope->budget->currency) }}</td><td>{{ \App\Services\Scm\Decimal::money($usage['available_minor'], $line->scope->budget->currency) }}</td></tr>
                @empty<tr><td colspan="8">Belum ada alokasi anggaran untuk departemen yang dapat Anda akses.</td></tr>@endforelse
            </tbody></table></div>{{ $lines->links() }}
        </section>
    @endif
</div>
</x-filament-panels::page>
