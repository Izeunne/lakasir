@php
    $record = $getRecord();
    $imageUrl = $record->hero_image ?: asset('images/food-placeholder.svg');
@endphp
<div class="pos-product-img-box" style="margin: 0 0 0.35rem 0; width: 100%; flex: 1 1 100%; height: 130px; min-height: 130px; max-height: 130px; overflow: hidden; border-top-left-radius: 0.75rem; border-top-right-radius: 0.75rem; background-color: #f3f4f6;">
    <img
        src="{{ $imageUrl }}"
        alt="{{ $record->name }}"
        class="pos-product-image"
        style="width: 100% !important; min-width: 100% !important; height: 100% !important; object-fit: cover !important; display: block !important; border-top-left-radius: 0.75rem !important; border-top-right-radius: 0.75rem !important;"
        onerror="this.src='{{ asset('images/food-placeholder.svg') }}'"
        loading="lazy"
    />
</div>
