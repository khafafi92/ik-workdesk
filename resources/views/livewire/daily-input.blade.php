{{-- View ini milik app/Livewire/DailyInput.php.
     Form memakai wire:submit.prevent="save", jadi tombol submit akan memanggil method save()
     dan menyimpan data ke tabel daily_reports. --}}

<div class="daily-input-component">
<style>
    .daily-worksheet {
        border-collapse: collapse;
        font-family: Calibri, Arial, sans-serif;
        table-layout: fixed;
        width: max-content;
        margin: 0 auto;
        /* FIX: tabel terpusat */
    }

    .daily-worksheet th,
    .daily-worksheet td {
        border-color: #000 !important;
        border-width: 1px !important;
    }

    .daily-worksheet [data-excel-cell="daily-reading"] {
        height: 15px !important;
        padding: 0 !important;
        font-size: 8px !important;
        line-height: 1 !important;
    }

    .daily-worksheet td input {
        height: 15px !important;
        padding: 0 !important;
    }

    .daily-worksheet .average-value {
        padding-right: 4px !important;
    }

    .daily-worksheet .average-label {
        padding-left: 3px !important;
        padding-right: 2px !important;
    }

    .daily-worksheet .average-value input {
        padding-right: 4px !important;
    }

    .daily-worksheet .average-unit {
        padding-left: 4px !important;
    }

    .daily-worksheet textarea {
        padding: 1px 2px !important;
    }

    .daily-worksheet select,
    .daily-worksheet input,
    .daily-worksheet textarea {
        font-family: Calibri, Arial, sans-serif;
    }

    /* FIX: wrapper page terpusat di layar */
    .daily-report-page {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    /* FIX: section scroll tetap full width tapi konten terpusat */
    .daily-print-wrapper {
        width: 100%;
        overflow-x: auto;
        display: flex;
        justify-content: center;
        /* FIX: tabel di tengah */
    }

    /* FIX: tombol-tombol bawah juga terpusat */
    .daily-actions {
        width: 100%;
        max-width: max-content;
        margin: 0 auto;
    }

    .signature-box {
        padding: 0 !important;
        vertical-align: top;
    }

    .signature-title {
        border-bottom: 1px solid #000;
        padding: 3px 4px;
        text-align: center;
        font-weight: 900;
    }

    .signature-space {
        height: 165px;
        min-height: 165px;
    }

    @media print {
        @page {
            size: A4 landscape;
            margin: 5mm;
        }

        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            background: white !important;
        }

        .no-print {
            display: none !important;
        }

        .daily-print-wrapper {
            overflow: visible !important;
            width: 100% !important;
            max-width: none !important;
            justify-content: flex-start !important;
            /* saat print tidak perlu centering */
        }

        .daily-worksheet {
            zoom: 0.82;
            width: max-content !important;
            min-width: 0 !important;
            margin: 0 !important;
        }

        .daily-worksheet th,
        .daily-worksheet td {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }

        .daily-worksheet thead {
            display: table-row-group !important;
        }

        .signature-row {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .signature-title {
            padding: 1pt 2pt !important;
        }

        .signature-space {
            height: 100pt !important;
            min-height: 100pt !important;
        }

        input,
        textarea,
        select {
            border: none !important;
            box-shadow: none !important;
            outline: none !important;
            background: transparent !important;
            color: black !important;
            appearance: none !important;
            -webkit-appearance: none !important;
        }
    }
</style>

<div class="daily-report-page min-h-screen w-full max-w-none bg-white px-4 py-2"
    style="font-family: Calibri, Arial, sans-serif;">
    {{-- Form utama Daily Input. Semua input wire:model terhubung ke property public di DailyInput.php. --}}
    <form wire:submit.prevent="save"
        onsubmit="if (! window.Livewire) { alert('Halaman belum siap menyimpan. Refresh halaman lalu coba lagi.'); return false; }"
        class="w-full space-y-4">

        @if (session()->has('success'))
        <div class="no-print border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800">
            {{ session('success') }}
        </div>
        @endif

        @if ($errors->any())
        <div class="no-print border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-700">
            <div class="font-semibold">Data input belum lengkap.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <section class="daily-print-wrapper">
            <table class="daily-worksheet table-fixed border-collapse bg-white text-[8px] text-black">
                <colgroup>
                    <col style="width: 24px;">
                    <col style="width: 128px;">
                    <col style="width: 38px;">
                    <col style="width: 42px;">
                    <col style="width: 42px;">
                    <col style="width: 45px;">

                    @foreach($timeColumns as $time)
                    <col style="width: 24px;">
                    @endforeach

                    <col style="width: 75px;">
                    <col style="width: 42px;">
                    <col style="width: 24px;">
                </colgroup>

                <thead>
                    <tr>
                        <th colspan="2" rowspan="4"
                            class="border-l border-t border-black bg-white px-4 py-3 text-left align-middle">
                            <img src="{{ asset('images/logo-kpmog-daily.png') }}" alt="KPMOG"
                                class="h-14 w-auto object-contain">
                        </th>

                        <th colspan="4" class="border-t border-black bg-white px-1 py-1 text-left align-middle">
                            <div class="grid grid-cols-[56px_8px_56px_1fr] items-center gap-1">
                                <span class="text-right font-bold">Unit</span>
                                <span class="text-center font-bold">:</span>

                                <select wire:model.live="selectedUnit"
                                    class="h-8 w-12 border border-blue-500 bg-white text-center text-3xl font-black text-orange-500 outline-none">
                                    <option value=""></option>
                                    @foreach($unitOptions as $unit)
                                    <option value="{{ $unit }}">{{ $unit }}</option>
                                    @endforeach
                                </select>

                                <span class="font-bold">L-072</span>
                            </div>
                        </th>

                        <th colspan="12"
                            class="border-t border-black bg-white px-1 py-0 text-center text-xs font-black">
                            <div>DAILY REPORT - UNIT GAS COMPRESSOR</div>
                            <div class="font-bold">LTRO-01 B Compressor Package Rental Service</div>
                        </th>

                        <th colspan="3" rowspan="3"
                            class="border-r border-t border-black px-1 py-1 text-center align-middle">
                            <img src="{{ asset('images/logo-skk-medco.png') }}" alt="SKK Migas MedcoEnergi"
                                class="mx-auto h-12 w-auto object-contain">
                        </th>
                    </tr>

                    <tr>
                        <th colspan="4" class="bg-white px-1 py-1 text-left">
                            <div class="grid grid-cols-[56px_8px_1fr] items-center gap-1">
                                <span class="text-right font-bold">Date</span>
                                <span class="text-center font-bold">:</span>

                                <input type="date" wire:model.live="report_date"
                                    class="h-6 w-28 border border-gray-300 bg-white px-1 text-center text-[10px] font-bold outline-none focus:bg-cyan-50">
                            </div>
                        </th>

                        <th colspan="12" class="bg-white px-1 py-1 text-left">
                            <div class="grid grid-cols-[96px_8px_1fr] items-center gap-1">
                                <span class="text-right font-bold">Operator Day</span>
                                <span class="text-center font-bold">:</span>

                                <input type="text" wire:model.live.debounce.500ms="operator_day"
                                    class="h-6 min-w-[150px] border border-gray-300 bg-white px-1 text-left text-[10px] font-semibold outline-none focus:bg-cyan-50">
                            </div>
                        </th>
                    </tr>

                    <tr>
                        <th colspan="4" class="bg-white px-1 py-1 text-left">
                            <div class="grid grid-cols-[56px_8px_1fr] items-center gap-1">
                                <span class="text-right font-bold">Day</span>
                                <span class="text-center font-bold">:</span>

                                <span
                                    class="inline-block min-w-[70px] border border-gray-300 bg-white px-1 py-1 font-semibold">
                                    {{ $report_date ? \Carbon\Carbon::parse($report_date)->translatedFormat('l') : '-'
                                    }}
                                </span>
                            </div>
                        </th>

                        <th colspan="12" class="bg-white px-1 py-1 text-left">
                            <div class="grid grid-cols-[96px_8px_1fr] items-center gap-1">
                                <span class="text-right font-bold">Operator Night</span>
                                <span class="text-center font-bold">:</span>

                                <input type="text" wire:model.live.debounce.500ms="operator_night"
                                    class="h-6 min-w-[150px] border border-gray-300 bg-white px-1 text-left text-[10px] font-semibold outline-none focus:bg-cyan-50">
                            </div>
                        </th>
                    </tr>

                    <tr>
                        <th colspan="19" class="border-r border-black bg-white px-1 py-1"></th>
                    </tr>

                    <tr class="text-center font-black">
                        <th rowspan="3" class="border border-black px-1 py-1">No</th>
                        <th rowspan="3" class="border border-black px-1 py-1">DESCRIPTION</th>
                        <th rowspan="3" class="border border-black px-1 py-1">Unit</th>
                        <th colspan="2" class="border border-black px-1 py-1">Alarm , Shutdown</th>
                        <th rowspan="3" class="border border-black px-1 py-1">Normal<br>Range</th>
                        <th colspan="{{ count($timeColumns) }}" class="border border-black px-1 py-1">RECORD TIME</th>
                        <th colspan="3" rowspan="3"
                            class="border border-black px-1 py-1 text-center text-xs font-black">
                            AVERAGE
                        </th>
                    </tr>

                    <tr class="text-center font-black">
                        <th class="border border-black px-1 py-1">LA / LSS</th>
                        <th class="border border-black px-1 py-1">HA / HHS</th>

                        @foreach($timeColumns as $time)
                        <th rowspan="2" class="border border-black p-0 text-[7px] leading-none">
                            {{ $time['label'] }}
                        </th>
                        @endforeach
                    </tr>

                    <tr class="text-center font-black">
                        <th class="border border-black px-1 py-1"></th>
                        <th class="border border-black px-1 py-1"></th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td class="border border-black px-1 py-1 text-center font-black">A</td>
                        <td class="border border-black bg-white px-1 py-1 font-black">ENGINE PARAMETER</td>
                        <td class="border border-black bg-white px-1 py-1"></td>
                        <td class="border border-black bg-white px-1 py-1"></td>
                        <td class="border border-black bg-white px-1 py-1"></td>
                        <td class="border border-black bg-white px-1 py-1"></td>

                        <td colspan="{{ count($timeColumns) }}"
                            class="border border-black bg-white px-1 py-1 text-center font-black">
                            ENGINE READING
                        </td>

                        <td colspan="3" class="border-l border-r border-black bg-white px-1 py-1"></td>
                    </tr>

                    @foreach($engineParameters as $parameter)
                    <tr wire:key="engine-row-{{ $parameter['number'] }}" class="h-[18px]">
                        <td class="border border-black px-1 text-center">{{ $parameter['number'] }}</td>

                        <td class="border border-black bg-white px-1 font-semibold">
                            {{ $parameter['description'] }}
                        </td>

                        <td class="border border-black bg-white px-1 text-center">
                            {{ $parameter['unit'] }}
                        </td>

                        <td class="border border-black bg-white px-1 text-center">
                            {{ $parameter['low_alarm'] }}
                        </td>

                        <td class="border border-black bg-white px-1 text-center">
                            {{ $parameter['high_alarm'] }}
                        </td>

                        <td class="border border-black bg-white px-1 text-center">
                            {{ $parameter['normal_range'] }}
                        </td>

                        @foreach($timeColumns as $time)
                        <td class="border border-black p-0">
                            <input type="text"
                                wire:model.live.debounce.500ms="dailyValues.engine.{{ $parameter['number'] }}.{{ $time['key'] }}"
                                data-excel-cell="daily-reading" data-excel-paste="daily-reading"
                                data-excel-section="engine" data-excel-row="{{ $parameter['number'] }}"
                                data-excel-col="{{ $loop->index }}"
                                class="h-[15px] w-full border-0 p-0 text-center text-[8px] outline-none focus:bg-cyan-50">
                        </td>
                        @endforeach

                        @if(isset($averagePanelRows[$loop->index]))
                        <td
                            class="average-label whitespace-normal break-words border border-gray-300 bg-white px-1 leading-tight font-black">
                            {{ $averagePanelRows[$loop->index]['label'] }}
                        </td>

                        <td class="average-value border border-gray-300 px-1 text-right font-black">
                            {{ $averagePanelRows[$loop->index]['value'] !== null
                            ? number_format($averagePanelRows[$loop->index]['value'], 2)
                            : '' }}
                        </td>

                        <td class="average-unit border-r border-gray-300 px-1 text-left font-black">
                            {{ $averagePanelRows[$loop->index]['unit'] }}
                        </td>
                        @else
                        <td colspan="3" class="border-r border-gray-300 bg-white px-1"></td>
                        @endif
                    </tr>
                    @endforeach

                    <tr>
                        <td class="border border-black px-1 py-1 text-center font-black">B</td>
                        <td class="border border-black bg-white px-1 py-1 font-black">COMPRESSOR PARAMETER</td>
                        <td class="border border-black bg-white px-1"></td>
                        <td class="border border-black bg-white px-1"></td>
                        <td class="border border-black bg-white px-1"></td>
                        <td class="border border-black bg-white px-1"></td>

                        <td colspan="{{ count($timeColumns) }}"
                            class="border border-black bg-white px-1 py-1 text-center font-black">
                            COMPRESSOR READING
                        </td>

                        <td class="border border-gray-300 px-1 font-black">Running :</td>

                        <td class="average-value border border-gray-300 p-0">
                            <input type="number" step="0.01" wire:model.live.debounce.500ms="running_hours"
                                class="h-[18px] w-full border-0 px-1 text-right text-[10px] font-black outline-none focus:bg-cyan-50">
                        </td>

                        <td class="average-unit border-r border-gray-300 px-1 text-left font-black">Hours</td>
                    </tr>

                    @foreach($compressorParameters as $parameter)
                    <tr wire:key="compressor-row-{{ $parameter['number'] }}" class="h-[18px]">
                        <td class="border border-black px-1 text-center">{{ $parameter['number'] }}</td>

                        <td class="border border-black bg-white px-1 font-semibold">
                            {{ $parameter['description'] }}
                        </td>

                        <td class="border border-black bg-white px-1 text-center">
                            {{ $parameter['unit'] }}
                        </td>

                        <td class="border border-black bg-white px-1 text-center">
                            {{ $parameter['low_alarm'] }}
                        </td>

                        <td class="border border-black bg-white px-1 text-center">
                            {{ $parameter['high_alarm'] }}
                        </td>

                        <td class="border border-black bg-white px-1 text-center">
                            {{ $parameter['normal_range'] }}
                        </td>

                        @foreach($timeColumns as $time)
                        <td class="border border-black p-0">
                            <input type="text"
                                wire:model.live.debounce.500ms="dailyValues.compressor.{{ $parameter['number'] }}.{{ $time['key'] }}"
                                data-excel-cell="daily-reading" data-excel-paste="daily-reading"
                                data-excel-section="compressor" data-excel-row="{{ $parameter['number'] }}"
                                data-excel-col="{{ $loop->index }}"
                                class="h-[15px] w-full border-0 p-0 text-center text-[8px] outline-none focus:bg-cyan-50">
                        </td>
                        @endforeach

                        @if(isset($compressorAverageCells[$parameter['number']]))
                        <td
                            class="average-label whitespace-normal break-words border border-gray-300 px-1 leading-tight font-black {{ $compressorAverageCells[$parameter['number']]['class'] ?? '' }}">
                            {{ $compressorAverageCells[$parameter['number']]['label'] ?? '' }}
                        </td>

                        <td
                            class="average-value border border-gray-300 p-0 text-right font-black {{ $compressorAverageCells[$parameter['number']]['class'] ?? '' }}">

                            @if($parameter['number'] === 1)
                            <input type="number" step="0.01" wire:model.live.debounce.500ms="standby_hours"
                                class="h-[18px] w-full border-0 bg-transparent px-1 text-right text-[10px] font-black outline-none focus:bg-cyan-50">

                            @elseif($parameter['number'] === 2)
                            <input type="number" step="0.01" wire:model.live.debounce.500ms="down_reactive_hours"
                                class="h-[18px] w-full border-0 bg-transparent px-1 text-right text-[10px] font-black outline-none focus:bg-cyan-50">

                            @elseif($parameter['number'] === 6)
                            <input type="text" wire:model="shutdown_indication"
                                class="h-[18px] w-full border-0 bg-transparent px-1 text-right text-[10px] font-black outline-none focus:bg-cyan-50">

                            @elseif($parameter['number'] === 12)
                            <input type="number" step="0.01" wire:model.live.debounce.500ms="last_stock_oil"
                                class="h-[18px] w-full border-0 bg-transparent px-1 text-right text-[10px] font-black outline-none focus:bg-cyan-50">

                            @elseif($parameter['number'] === 13)
                            <input type="number" step="0.01" wire:model.live.debounce.500ms="received_oil"
                                class="h-[18px] w-full border-0 bg-transparent px-1 text-right text-[10px] font-black outline-none focus:bg-cyan-50">

                            @elseif($parameter['number'] === 14)
                            <input type="number" step="0.01" wire:model.live.debounce.500ms="used_oil"
                                class="h-[18px] w-full border-0 bg-transparent px-1 text-right text-[10px] font-black outline-none focus:bg-cyan-50">

                            @elseif(($compressorAverageCells[$parameter['number']]['value'] ?? '') !== '')
                            {{ number_format($compressorAverageCells[$parameter['number']]['value'], 2) }}
                            @endif
                        </td>

                        <td
                            class="average-unit border-r border-gray-300 px-1 text-left font-black {{ $compressorAverageCells[$parameter['number']]['class'] ?? '' }}">
                            {{ $compressorAverageCells[$parameter['number']]['unit'] ?? '' }}
                        </td>
                        @else
                        <td colspan="3" class="border-r border-gray-300 p-0">
                            <input type="text" wire:model="average_notes.{{ $parameter['number'] }}"
                                class="h-[18px] w-full border-0 px-1 text-[10px] outline-none focus:bg-cyan-50">
                        </td>
                        @endif
                    </tr>
                    @endforeach

                    <tr>
                        <td colspan="18" class="border border-black px-1 py-1 text-left font-black">
                            Activity :
                        </td>

                        <td colspan="3" class="border-r border-gray-300 p-0">
                            <input type="text" wire:model="remark_used_oil"
                                class="h-[22px] w-full border-0 bg-transparent px-1 text-[10px] font-semibold outline-none focus:bg-cyan-50">
                        </td>
                    </tr>

                    <tr>
                        <td colspan="18" class="border-b border-l border-r border-black p-0">
                            <textarea wire:model="activity" rows="3"
                                class="w-full resize-none border-0 px-2 py-1 text-[10px] outline-none focus:bg-cyan-50"></textarea>
                        </td>

                        <td colspan="3" class="border-b border-r border-black p-0">
                            <textarea wire:model="remark_used_oil" rows="3"
                                class="w-full resize-none border-0 bg-transparent px-2 py-1 text-[10px] outline-none focus:bg-cyan-50"></textarea>
                        </td>
                    </tr>

                    <tr class="signature-row">
                        <td colspan="7" class="signature-box border-l border-r border-b border-black">
                            <div class="signature-title">Operator KPMOG</div>
                            <div class="signature-space"></div>
                        </td>
                        <td colspan="7" class="signature-box border border-b border-black">
                            <div class="signature-title">Lead O&amp;M KPMOG</div>
                            <div class="signature-space"></div>
                        </td>
                        <td colspan="7" class="signature-box border-r border-b border-l border-black">
                            <div class="signature-title">Prod Supervisor MEPG</div>
                            <div class="signature-space"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <div class="daily-actions no-print daily-action-bar">
            <a href="{{ route('home') }}" class="btn btn-dark">
                <i class="ti ti-home" aria-hidden="true"></i> Home
            </a>

            <a href="{{ route('monitoring') }}" class="btn btn-dark">
                <i class="ti ti-arrow-left" aria-hidden="true"></i> Monitoring
            </a>

            <a href="{{ route('daily.reports.index') }}" class="btn btn-blue">
                <i class="ti ti-table" aria-hidden="true"></i> View Daily
            </a>

            <button type="button" onclick="window.print()" class="btn btn-warning">
                <i class="ti ti-printer" aria-hidden="true"></i> Cetak A4
            </button>

            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="btn btn-primary disabled:cursor-wait disabled:opacity-60">
                <i class="ti ti-device-floppy" aria-hidden="true"></i>
                <span wire:loading.remove wire:target="save">Simpan Input Daily</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </button>
        </div>
    </form>
</div>
<script>
    if (! window.dailyExcelPasteInitialized) {
        window.dailyExcelPasteInitialized = true;

        document.addEventListener('paste', function (event) {
            const target = event.target.closest('[data-excel-paste="daily-reading"]');

            if (! target) {
                return;
            }

            const text = event.clipboardData?.getData('text/plain') ?? '';

            if (! text.includes('\t') && ! text.includes('\n')) {
                return;
            }

            event.preventDefault();

            const rows = text
                .replace(/\r\n/g, '\n')
                .replace(/\r/g, '\n')
                .replace(/\n$/, '')
                .split('\n')
                .map((row) => row.split('\t'));

            const section = target.dataset.excelSection;
            const startRow = Number(target.dataset.excelRow);
            const startCol = Number(target.dataset.excelCol);
            const selector = `[data-excel-paste="daily-reading"][data-excel-section="${section}"]`;
            const inputs = Array.from(document.querySelectorAll(selector));
            const inputMap = new Map();

            inputs.forEach((input) => {
                inputMap.set(`${input.dataset.excelRow}:${input.dataset.excelCol}`, input);
            });

            rows.forEach((cells, rowOffset) => {
                cells.forEach((value, colOffset) => {
                    const input = inputMap.get(`${startRow + rowOffset}:${startCol + colOffset}`);

                    if (! input) {
                        return;
                    }

                    input.value = value.trim();
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            const componentRoot = target.closest('[wire\\:id]');
            const componentId = componentRoot?.getAttribute('wire:id');

            if (componentId && window.Livewire?.find) {
                window.Livewire
                    .find(componentId)
                    .call('pasteReadingBlock', section, startRow, startCol, rows);
            }
        });

        document.addEventListener('keydown', function (event) {
            const target = event.target.closest('[data-excel-cell="daily-reading"]');

            if (! target) {
                return;
            }

            const moves = {
                ArrowRight: [0, 1],
                ArrowLeft: [0, -1],
                ArrowDown: [1, 0],
                ArrowUp: [-1, 0],
                Enter: [1, 0],
            };

            const move = moves[event.key];

            if (! move) {
                return;
            }

            event.preventDefault();

            const section = target.dataset.excelSection;
            const row = Number(target.dataset.excelRow) + move[0];
            const col = Number(target.dataset.excelCol) + move[1];

            const next = document.querySelector(
                `[data-excel-cell="daily-reading"][data-excel-section="${section}"][data-excel-row="${row}"][data-excel-col="${col}"]`
            );

            if (! next) {
                return;
            }

            next.focus();
            next.select();
        });
    }
</script>
</div>

