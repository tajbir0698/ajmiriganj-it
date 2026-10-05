<x-print-layout :title="$title" :periodText="'System Activity & Security Audit Trail'">
    <table>
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>User / Actor</th>
                <th>Event / Action</th>
                <th>Subject Model</th>
                <th>Subject ID</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td style="color: #6b7280;">{{ $r['date'] }}</td>
                    <td style="font-weight: 500;">{{ $r['causer_name'] ?? 'System' }}</td>
                    <td><span class="badge badge-warning">{{ strtoupper($r['event'] ?? $r['description']) }}</span></td>
                    <td style="color: #4b5563;">{{ class_basename($r['subject_type'] ?? '') }}</td>
                    <td style="color: #6b7280;">#{{ $r['subject_id'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="padding: 20px; color: #9ca3af;">
                        No audit activities recorded.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-print-layout>
