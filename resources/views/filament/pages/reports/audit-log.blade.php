<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $logs = $this->logs;
        @endphp

        <x-filament::section>
            <x-slot name="heading">
                Audit Trail & Activity Logs
            </x-slot>

            <div class="overflow-x-auto -mx-6 -my-4">
                <table class="w-full text-left text-sm divide-y divide-gray-200 dark:divide-white/10 report-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Action</th>
                            <th>Subject</th>
                            <th>User</th>
                            <th>Changes / Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse($logs as $log)
                            <tr>
                                <td class="text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap">
                                    {{ $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : 'N/A' }}
                                </td>
                                <td>
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300">
                                        {{ $log->description }}
                                    </span>
                                </td>
                                <td class="text-gray-600 dark:text-gray-300 text-xs">
                                    {{ class_basename($log->subject_type ?? '') }} #{{ $log->subject_id }}
                                </td>
                                <td class="text-gray-700 dark:text-gray-300 text-xs font-medium">
                                    {{ $log->causer ? $log->causer->name : 'System' }}
                                </td>
                                <td class="text-xs text-gray-500 dark:text-gray-400 font-mono max-w-md truncate">
                                    {{ json_encode($log->properties?->toArray() ?? [], JSON_UNESCAPED_SLASHES) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-gray-400 dark:text-gray-500">No activity log entries recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div class="pt-4 border-t border-gray-100 dark:border-white/5">
                    {{ $logs->links() }}
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
