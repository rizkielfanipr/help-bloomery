{{--
    Footer of the Sourcing Bahan list: the shared ESB product picker (identical to BOM
    "Tambah Komponen"). The stable wire:partial root lets the page re-render only this block when the
    picker opens or closes alongside an action modal, without a full page render.
--}}
<div wire:partial="sourcing-product-picker">
    @include('filament.helpdesk.partials.esb-product-picker-modal', [
        'pickerTitle' => 'Pilih Bahan Sourcing',
        'pickerSelectMethod' => 'selectSourcingProduct',
        'pickerCloseAction' => 'closeInlineProductPicker',
    ])
</div>
