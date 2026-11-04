@props(['kind' => 'optional'])
@php
    $requirementKind = in_array($kind, ['required', 'submission', 'conditional'], true) ? 'required' : 'optional';
@endphp
<span {{ $attributes->class(['ml-1 text-xs font-normal', 'text-rose-600' => $requirementKind === 'required', 'text-slate-500' => $requirementKind === 'optional']) }} data-field-requirement="{{ $requirementKind }}"><span aria-hidden="true" data-requirement-symbol>{{ $requirementKind === 'required' ? '*' : '(Optional)' }}</span><span class="sr-only" data-requirement-label>{{ $requirementKind === 'required' ? 'Required' : 'Optional' }}</span></span>
