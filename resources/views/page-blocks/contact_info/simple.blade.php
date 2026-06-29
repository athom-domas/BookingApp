{{-- Variables: $content['title'], $settings['show_phone'], $settings['show_address'],
     $settings['show_hours'], $settings['contacts_position'], $profile (SalonProfile, may be null), $block --}}
@php
    $contactsRight = ($settings['contacts_position'] ?? 'right') === 'right';
@endphp
<section id="contact-{{ $block->id }}" class="sf-section sf-section-alt">
    <div class="sf-inner">
        @if(!empty($content['title']))
            <h2 class="sf-heading">{{ $content['title'] }}</h2>
            <div class="sf-rule"></div>
        @endif

        @if($profile)
        @php
            $hasContacts = ($settings['show_address'] ?? true) && (
                $profile->address || $profile->phone || $profile->whatsapp_number ||
                $profile->instagram_url || $profile->facebook_url
            );
            $hasHours = ($settings['show_hours'] ?? true) && !empty($profile->opening_hours);

            $days   = ['mon'=>'Lunedì','tue'=>'Martedì','wed'=>'Mercoledì','thu'=>'Giovedì','fri'=>'Venerdì','sat'=>'Sabato','sun'=>'Domenica'];
            $dayMap = ['Mon'=>'mon','Tue'=>'tue','Wed'=>'wed','Thu'=>'thu','Fri'=>'fri','Sat'=>'sat','Sun'=>'sun'];
            $todayKey = $dayMap[now()->format('D')] ?? '';
        @endphp

        @if($contactsRight && $hasHours && $hasContacts)
        {{-- Two columns: hours left | contacts right --}}
        <div class="sf-info-grid sf-info-grid--dual">
            <div>
                @include('page-blocks.contact_info._hours', compact('profile', 'days', 'todayKey'))
            </div>
            <div>
                @include('page-blocks.contact_info._contacts', compact('profile', 'settings'))
            </div>
        </div>
        @else
        {{-- Stacked --}}
        @if($hasHours)
            @include('page-blocks.contact_info._hours', compact('profile', 'days', 'todayKey'))
        @endif
        @if($hasContacts)
            <div style="margin-top:2rem">
                @include('page-blocks.contact_info._contacts', compact('profile', 'settings'))
            </div>
        @endif
        @endif

        @endif
    </div>
</section>
