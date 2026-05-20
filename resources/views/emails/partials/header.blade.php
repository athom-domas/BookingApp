@php $salonProfile = \App\Models\SalonProfile::current(); @endphp
<div style="background-color:{{ preg_replace('/[^#0-9a-fA-F]/', '', $salonProfile->primary_color) }};padding:20px 32px;display:flex;align-items:center;gap:12px;">
    @if($salonProfile->logoUrl())
        <img src="{{ $salonProfile->logoUrl() }}" alt="{{ e($salonProfile->name) }}" style="width:40px;height:40px;border-radius:6px;object-fit:contain;">
    @endif
    <span style="color:#ffffff;font-weight:600;font-size:1rem;">{{ e($salonProfile->name) }}</span>
</div>
