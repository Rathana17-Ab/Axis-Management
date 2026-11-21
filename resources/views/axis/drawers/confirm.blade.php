{{-- Shared confirmation, driven by AxisDrawer.confirm({...}). Shown as a small centred dialog (see axis-sales-theme.css); sits above every drawer. --}}
<x-axis.drawer id="axis-confirm-drawer" mode="delete" icon="fa-trash-can" size="sm" layer="260"
               title="Are you sure?" description=""
               primary-action="AxisDrawer.acceptConfirm()" submit-label="Delete">
    <div data-confirm-message class="hidden rounded-xl bg-rose-500/10 border border-rose-400/25 px-4 py-3">
        <p data-confirm-body class="text-sm text-rose-100 leading-relaxed"></p>
    </div>
    <p class="text-sm text-slate-400 mt-2">This can't be undone.</p>
</x-axis.drawer>
