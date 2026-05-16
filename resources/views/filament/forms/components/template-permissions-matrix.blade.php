@php
    $statePath = $getStatePath();
    $state = $getState() ?? [];
    $templates = $field->getTemplates();
    $actions = $field::$crudActions;
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if ($templates->isEmpty())
        <p class="text-sm text-gray-400 italic">Belum ada template form aktif.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="py-2 pr-6 text-left font-medium text-gray-600">Template Form</th>
                        @foreach ($actions as $label)
                            <th class="py-2 px-4 text-center font-medium text-gray-600">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($templates as $template)
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50/50">
                            <td class="py-2.5 pr-6 font-medium text-gray-800">
                                {{ $template->name }}
                                @unless ($template->is_active)
                                    <span class="ml-1 text-xs text-gray-400">(nonaktif)</span>
                                @endunless
                            </td>
                            @foreach (array_keys($actions) as $action)
                                @php $permId = $field->getPermissionId($template->id, $action); @endphp
                                <td class="py-2.5 px-4 text-center">
                                    @if ($permId)
                                        <input
                                            type="checkbox"
                                            wire:model.live="{{ $statePath }}"
                                            value="{{ $permId }}"
                                            @checked(in_array((string) $permId, array_map('strval', $state)))
                                            class="fi-checkbox-input rounded border-gray-300"
                                            wire:key="{{ $statePath }}.{{ $template->id }}.{{ $action }}"
                                        />
                                    @else
                                        <span class="text-gray-300 text-xs">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-dynamic-component>
