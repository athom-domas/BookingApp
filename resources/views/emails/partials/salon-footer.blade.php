@php $salonProfile = \App\Models\SalonProfile::current(); @endphp
@if($salonProfile->phone || $salonProfile->address || $salonProfile->website)
<div style="padding:12px 32px;font-size:0.75rem;color:#9ca3af;border-top:1px solid #f3f4f6;">
    @if($salonProfile->phone)<span style="margin-right:12px;">{{ e($salonProfile->phone) }}</span>@endif
    @if($salonProfile->address)<span style="margin-right:12px;">{{ e($salonProfile->address) }}</span>@endif
    @if($salonProfile->website)<span>{{ e($salonProfile->website) }}</span>@endif
</div>
@endif
