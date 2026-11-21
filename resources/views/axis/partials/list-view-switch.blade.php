{{-- Table / Cards / Board switch for a list page. $list: "projects" | "modules" (matches setListView()). --}}
<div class="axis-view-switch inline-flex items-center" role="group" aria-label="Show the list as">
    @foreach ([['table', 'fa-table-list', 'Table'], ['cards', 'fa-grip', 'Cards'], ['board', 'fa-table-columns', 'Board by status']] as [$mode, $icon, $label])
        <button type="button" onclick="setListView('{{ $list }}', '{{ $mode }}')" data-list-view="{{ $list }}:{{ $mode }}"
                @class(['axis-view-switch__btn', 'is-active' => $mode === 'table']) title="{{ $label }}" aria-label="{{ $label }}" aria-pressed="{{ $mode === 'table' ? 'true' : 'false' }}">
            <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
        </button>
    @endforeach
</div>
