@if($medicine->stock_quantity === 0)
    <span class="status status-out">Out of stock</span>
@elseif($medicine->stock_quantity <= $medicine->minimum_stock)
    <span class="status status-low">Low stock</span>
@else
    <span class="status status-good">In stock</span>
@endif
