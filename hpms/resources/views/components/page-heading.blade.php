@props(['title', 'description' => null, 'actionClass' => null])
<div {{ $attributes->class(['page-heading']) }}>
    <div><h1>{{ $title }}</h1>@if($description)<p>{{ $description }}</p>@endif</div>
    @if(isset($action))<div class="heading-action{{ $actionClass ? ' '.$actionClass : '' }}">{{ $action }}</div>@endif
</div>
