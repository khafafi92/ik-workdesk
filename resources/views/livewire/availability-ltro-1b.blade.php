<div class="availability-page">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">

    <style>
        .availability-page .logo-box {
            background: transparent;
            padding: 0;
            border-radius: 0;
        }

        .availability-page .availability-controls {
            display: flex;
            gap: 0.5rem;
            align-items: end;
            flex-wrap: wrap;
        }

        .availability-page .field {
            display: grid;
            gap: 0.35rem;
        }

        .availability-page .field label {
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .availability-page .field select,
        .availability-page .field input {
            min-height: 34px;
            min-width: 190px;
            border: 0.5px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            color: #0f172a;
            font-size: 12px;
            padding: 0.45rem 0.6rem;
        }

        .availability-page .dark-table {
            min-width: 2460px;
        }

        .availability-page .dark-table input,
        .availability-page .dark-table textarea {
            width: 100%;
            border: 0;
            background: transparent;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.25;
            outline: none;
            padding: 0;
        }

        .availability-page .dark-table textarea {
            min-height: 34px;
            resize: vertical;
        }

        .availability-number {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .availability-readonly {
            color: #64748b !important;
            white-space: nowrap;
            text-align: center;
        }

        .availability-page .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            padding: 1rem;
            background: #f8fafc;
        }

        .availability-page .edit-note {
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
        }

        .availability-page .row-action {
            display: flex;
            justify-content: center;
        }
    </style>

    <header class="header">
        <div class="header-left">
            <div class="logo-box">
                <img src="{{ asset('img/brands/kpmog.png') }}" alt="KPMOG">
            </div>
            <div>
                <div class="header-title">AVAILABILITY LTRO-<span class="accent">1B</span></div>
                <div class="header-sub">Periode report mengikuti tanggal 21 sampai tanggal 20</div>
            </div>
        </div>

        <div class="header-right">
            <a href="{{ route('monitoring') }}" class="btn btn-dark">
                <i class="ti ti-arrow-left" aria-hidden="true"></i> Monitoring
            </a>
            <a href="{{ url('/panel') }}" class="btn btn-dark">
                <i class="ti ti-layout-dashboard" aria-hidden="true"></i> Kembali ke Dashboard
            </a>
        </div>
    </header>

    <main class="main">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Period Availability</div>
                    <div class="panel-sub">Pilih period aktif atau buat period baru sampai tanggal 20.</div>
                </div>

                <div class="availability-controls">
                    <div class="field">
                        <label>Period</label>
                        <select wire:model.live="selectedPeriodStart">
                            <option value="">Belum ada period</option>
                            @foreach($periods as $period)
                                <option value="{{ $period['value'] }}">{{ $period['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <form wire:submit.prevent="createPeriod" class="availability-controls">
                        <div class="field">
                            <label>Buat period sampai tanggal 20</label>
                            <input type="month" wire:model="newPeriodEndMonth">
                        </div>
                        <button type="submit" class="btn btn-blue">
                            <i class="ti ti-calendar-plus" aria-hidden="true"></i> Buat Period
                        </button>
                    </form>

                    @if($selectedPeriodStart)
                        <button
                            type="button"
                            class="btn btn-danger"
                            wire:click="deletePeriod"
                            wire:confirm="Hapus semua data pada period ini?"
                        >
                            <i class="ti ti-trash" aria-hidden="true"></i> Hapus Period
                        </button>
                    @endif
                </div>
            </div>

            <div class="panel-body">
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-label">Total Days</div>
                        <div class="summary-value blue">{{ number_format($summary['days']) }}</div>
                        <div class="summary-caption">Hari</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Required Hours</div>
                        <div class="summary-value amber">{{ number_format($summary['total_required_hours'], 2) }}</div>
                        <div class="summary-caption">Hours</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Shutdown Hours</div>
                        <div class="summary-value amber">{{ number_format($summary['total_shutdown_hours'], 2) }}</div>
                        <div class="summary-caption">Hours</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Unplanned Hours</div>
                        <div class="summary-value amber">{{ number_format($summary['total_unplanned_hours'], 2) }}</div>
                        <div class="summary-caption">Hours</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Avg Availability</div>
                        <div class="summary-value blue">{{ number_format($summary['avg_availability'], 2) }}%</div>
                        <div class="summary-caption">Percent</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Avg DOE</div>
                        <div class="summary-value blue">{{ number_format($summary['avg_doe'], 2) }}%</div>
                        <div class="summary-caption">Percent</div>
                    </div>
                </div>
            </div>
        </section>

        @if (session()->has('success'))
            <div class="alert-success">
                <i class="ti ti-circle-check" aria-hidden="true"></i>
                {{ session('success') }}
            </div>
        @endif

        <section class="table-section">
            <div class="section-head">
                <span class="section-title">Availability LTRO-1B Records</span>
                <div class="section-line"></div>
                <span class="section-count">{{ count($rows) }} record</span>
            </div>

            <p class="scroll-hint">
                <i class="ti ti-arrows-horizontal" aria-hidden="true"></i> Geser untuk lihat semua kolom
            </p>

            @if(empty($rows))
                <div class="table-wrap">
                    <div class="empty-cell">Belum ada data. Buat period baru lalu isi data manual.</div>
                </div>
            @else
                <form wire:submit.prevent="save">
                    <div class="table-wrap">
                        <table class="dark-table">
                            <thead>
                                <tr>
                                    <th style="width:52px;">No</th>
                                    <th style="width:92px;">Day</th>
                                    <th style="width:96px;">Date</th>
                                    <th style="width:82px;">Flow Rate<br>MMSCFD</th>
                                    <th style="width:76px;">Rental<br>Code</th>
                                    <th style="width:90px;">Fuel Gas<br>MMSCFD</th>
                                    <th style="width:90px;">Spare Part<br>%</th>
                                    <th style="width:86px;">Unplan<br>Comp A</th>
                                    <th style="width:86px;">Shutdown<br>Comp A</th>
                                    <th style="width:86px;">Running<br>Comp A</th>
                                    <th style="width:86px;">Unplan<br>Comp B</th>
                                    <th style="width:86px;">Shutdown<br>Comp B</th>
                                    <th style="width:86px;">Running<br>Comp B</th>
                                    <th style="width:86px;">Unplan<br>Comp C</th>
                                    <th style="width:86px;">Shutdown<br>Comp C</th>
                                    <th style="width:86px;">Running<br>Comp C</th>
                                    <th style="width:90px;">Required<br>Hours</th>
                                    <th style="width:90px;">ASC</th>
                                    <th style="width:70px;">LPO</th>
                                    <th style="width:84px;">Reliability<br>%</th>
                                    <th style="width:84px;">Availability<br>%</th>
                                    <th style="width:84px;">DOE<br>%</th>
                                    <th class="left" style="width:270px;">Remark</th>
                                    <th style="width:92px;">PK100<br>Shutdown</th>
                                    <th style="width:86px;">LTRX A<br>RH</th>
                                    <th style="width:86px;">LTRX B<br>RH</th>
                                    <th style="width:86px;">PK101<br>RH</th>
                                    <th style="width:100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $index => $row)
                                    <tr>
                                        <td class="availability-readonly">{{ $row['row_no'] }}</td>
                                        <td class="availability-readonly">{{ $row['day_name'] }}</td>
                                        <td class="availability-readonly date-running">{{ \Carbon\Carbon::parse($row['report_date'])->format('d-M-y') }}</td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.flow_rate"></td>
                                        <td><input type="text" wire:model.defer="rows.{{ $index }}.rental_code"></td>
                                        <td><input class="availability-number" type="number" step="0.0001" wire:model.defer="rows.{{ $index }}.fuel_gas_consumption"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.spare_part_availability"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.comp_a_unplanned_shutdown_hours"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.comp_a_shutdown_hours"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.comp_a_running_hours"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.comp_b_unplanned_shutdown_hours"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.comp_b_shutdown_hours"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.comp_b_running_hours"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.comp_c_unplanned_shutdown_hours"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.comp_c_shutdown_hours"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.comp_c_running_hours"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.total_required_hours"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.availability_system_capacity"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.lpo"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.reliability_percent"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.availability_percent"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.doe_percent"></td>
                                        <td><textarea wire:model.defer="rows.{{ $index }}.remark"></textarea></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.pk100_shutdown_hours"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.ltrx_a_running_hours"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.ltrx_b_running_hours"></td>
                                        <td><input class="availability-number" type="number" step="0.01" wire:model.defer="rows.{{ $index }}.pk101_running_hours"></td>
                                        <td>
                                            <div class="row-action">
                                                <button
                                                    type="button"
                                                    class="btn btn-danger btn-mini"
                                                    wire:click="deleteRow({{ $row['id'] }})"
                                                    wire:confirm="Hapus baris tanggal {{ \Carbon\Carbon::parse($row['report_date'])->format('d-M-y') }}?"
                                                >
                                                    <i class="ti ti-trash" aria-hidden="true"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="form-actions" style="border-top:0.5px solid #e5e7eb;">
                        <span class="edit-note">Edit data langsung di tabel, lalu klik Simpan Data.</span>
                        <button type="submit" class="btn btn-blue" wire:loading.attr="disabled" wire:target="save">
                            <i class="ti ti-device-floppy" aria-hidden="true"></i>
                            <span wire:loading.remove wire:target="save">Simpan Data</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            @endif
        </section>
    </main>
</div>
