@if($blockClass !== null)
    @php
        $viewPath = ($blockClass)::viewFor($resolvedVariant);
    @endphp

    @if(view()->exists($viewPath))
        @include($viewPath, array_merge([
            'block'    => $block,
            'content'  => $block->content,
            'settings' => $block->settings,
            'business' => $business,
        ], $blockData))
    @else
        @if(config('app.debug'))
            <div style="background:#fee;padding:1rem;border:1px solid #f00;margin:1rem 0;">
                PageBlock: view not found: {{ $viewPath }}
            </div>
        @endif
    @endif
@endif
