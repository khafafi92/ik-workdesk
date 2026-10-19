@once
    <style>
        .it-review { min-width: 0; color: #334155; }
        .it-review-notice { padding: .6rem 1rem; border: 1px solid #cbd5e1; border-radius: .5rem; background: #f8fafc; margin-bottom: 1rem; font-size: .875rem; }
        .it-review-notice strong { color: #0f172a; margin-right: .4rem; }
        .it-review p { line-height: 1.6; }
        .it-review-toolbar { display: flex; flex-wrap: wrap; align-items: end; gap: .75rem; margin-bottom: .5rem; }
        .it-review-report-filters { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .it-review-field { display: grid; gap: .4rem; flex: 1 1 13rem; min-width: 0; }
        .it-review label { font-size: .875rem; font-weight: 600; color: #334155; }
        .it-review select { border: 1px solid #64748b; border-radius: .5rem; min-height: 2.75rem; width: 100%; max-width: 100%; padding: .5rem 2rem .5rem .75rem; font: inherit; background-color: #fff; color: #0f172a; }
        .it-review .fi-input-wrp { box-shadow: 0 0 0 1px #64748b; }
        .it-review .fi-tabs-item.fi-active { background: #0068d9; color: #fff; }
        .it-review .fi-tabs-item.fi-active .fi-tabs-item-label { color: #fff; }
        .it-review .fi-tabs { flex-wrap: wrap; gap: .25rem; }
        .it-review .fi-tabs-item { flex-shrink: 0; }
        .it-review :is(a, button, input, select, textarea, [tabindex="0"]):focus-visible { outline: 2px solid #005fcc; outline-offset: 3px; }
        .it-review button { min-width: 2.75rem; min-height: 2.75rem; }
        .it-review-actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.25rem; align-items: center; }
        .it-review-help { font-size: .8125rem; margin-bottom: .75rem; }
        .it-review-progress { font-size: .875rem; padding: .7rem 0; }
        .it-review-checklist { margin-top: 1rem; background: #fff; border: 1px solid #cbd5e1; border-radius: .75rem; padding: 1rem; }
        .it-review-checklist-table th:first-child { width: 30%; }
        .it-review-checklist-table th:nth-child(2) { width: 18%; }
        .it-review-checklist-table th:nth-child(3) { width: 22%; }
        .it-review-checklist-table select { min-width: 11.5rem; }
        .it-review-checklist-table .it-review-help { margin: .35rem 0 0; font-weight: 400; }
        .it-review-worksheet { padding: 0; border: 0; border-radius: 0; }
        .it-review-worksheet .it-review-toolbar { justify-content: space-between; align-items: center; }
        .it-review-worksheet .it-review-table-wrap { margin-top: 0; }
        .it-review .it-review-worksheet table { table-layout: fixed; min-width: 60rem; }
        .it-review .it-review-worksheet :is(th, td) { border: 1px solid #64748b; padding: .4rem .5rem; }
        .it-review-worksheet td { background: #fff; }
        .it-review-worksheet .it-review-worksheet-group th { width: auto; background: #fff; }
        .it-review-worksheet-group h2 { margin: 0; font-size: .875rem; font-weight: 700; color: #0f172a; }
        .it-review-worksheet .it-review-help { margin-top: .2rem; font-size: .75rem; }
        .it-review-worksheet :is(select, .it-review-value) { border: 0; border-radius: 0; }
        .it-review .it-review-worksheet :is(input, select, textarea):focus-visible { outline-offset: -2px; }
        .it-review-worksheet select { min-width: 0; }
        .it-review-finding-fields { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, .8fr); gap: .75rem; padding: .25rem 0 .5rem; }
        .it-review-checklist-table :is(th, td) { padding: .5rem .65rem; vertical-align: middle; }
        .it-review-value { min-height: 2.75rem; min-width: 0; width: 100%; border: 1px solid #64748b; border-radius: .5rem; padding: .5rem .65rem; color: #0f172a; background: #fff; }
        .it-review :is(input, select, textarea):disabled { background: #f1f5f9; color: #334155; }
        .it-review-row-error { display: block; color: #991b1b; font-size: .8125rem; margin-top: .25rem; }
        .it-review-counts { display: flex; flex-wrap: wrap; gap: .4rem; }
        .it-review .fi-btn { min-height: 2.75rem; }
        .it-review-table-wrap { overflow-x: auto; max-width: 100%; margin-top: .75rem; }
        .it-review table { width: 100%; min-width: 34rem; border-collapse: collapse; text-align: left; font-size: .875rem; }
        .it-review table.it-review-inspection-results { table-layout: fixed; }
        .it-review-inspection-results th:first-child { width: 30%; }
        .it-review-inspection-results th:nth-child(2) { width: 18%; }
        .it-review-inspection-results th:nth-child(3) { width: 22%; }
        .it-review th { font-weight: 600; background: #f8fafc; color: #334155; }
        .it-review :is(th, td) { padding: .8rem 1rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; overflow-wrap: anywhere; }
        .it-review a.it-review-link { color: #0056b3; text-decoration: underline; text-underline-offset: .2em; display: inline-flex; min-height: 2.75rem; align-items: center; }
        .it-review-section { margin-top: 1.5rem; }
        .it-review-section h2 { font-size: 1.125rem; font-weight: 600; color: #0f172a; margin-bottom: .5rem; }
        .it-review-inspection-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr)); gap: 1rem; margin: 1rem 0; }
        .it-review-inspection-meta dt { font-size: .875rem; font-weight: 600; }
        .it-review-inspection-meta dd { overflow-wrap: anywhere; }
        .it-review-result-text { white-space: pre-line; }
        .it-review-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr)); gap: .75rem; margin: 1rem 0; }
        .it-review-summary div { padding: .75rem 1rem; border: 1px solid #cbd5e1; border-radius: .5rem; }
        .it-review-summary dt { font-size: .875rem; }
        .it-review-summary dd { color: #0f172a; font-size: 1.4rem; font-weight: 600; margin-top: .2rem; }
        .it-review-status { display: inline-block; padding: .2rem .5rem; border-radius: .3rem; font-size: .8125rem; font-weight: 600; background: #f1f5f9; color: #334155; }
        .it-review-status[data-condition="normal"] { color: #166534; background: #f0fdf4; }
        .it-review-status[data-condition="warning"] { color: #854d0e; background: #fefce8; }
        .it-review-status[data-condition="critical"] { color: #991b1b; background: #fef2f2; }
        .it-review-loading { margin: .75rem 0; color: #334155; }
        .it-review-errors { padding: 1rem; border: 1px solid #991b1b; border-radius: .5rem; background: #fef2f2; color: #991b1b; margin-bottom: 1rem; }
        .it-review-errors ul { padding-left: 1.25rem; list-style: disc; }
        .it-review form { min-width: 0; }
        @media (max-width: 640px) {
            .it-review-toolbar { display: grid; grid-template-columns: minmax(0, 1fr); }
            .it-review-finding-fields { grid-template-columns: minmax(0, 1fr); }
            .it-review .fi-tabs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .it-review .fi-tabs-item { justify-content: center; }
            .it-review-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .it-review :is(th, td) { padding: .65rem; }
            .it-review-actions .fi-btn { flex: 1 1 11rem; }
        }
    </style>
@endonce
<div class="it-review-loading" role="status" wire:loading>Memuat perubahan...</div>
@if ($errors->any())
    <div class="it-review-errors" role="alert" tabindex="-1">
        <strong>Periksa isian berikut.</strong>
        <ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
@endif
