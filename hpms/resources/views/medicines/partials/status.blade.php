@if($medicine->stock_quantity === 0)
    <span class="status status-out">{{ __('medicines.out_of_stock') }}</span>
@elseif($medicine->stock_quantity <= $medicine->minimum_stock)
    <span class="status status-low">{{ __('medicines.low_stock') }}</span>
@else
    <span class="status status-good">{{ __('medicines.in_stock') }}</span>
@endif
