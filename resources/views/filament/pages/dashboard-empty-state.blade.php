<div class="col-span-full py-12 px-6 text-center bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm my-4">
    <div class="inline-flex p-4 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 mb-4">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
        </svg>
    </div>
    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">All dashboard cards are turned off</h3>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">All dashboard cards are turned off. You can turn them on in Dashboard Settings.</p>
    @if(auth()->user()?->isSuperAdmin())
        <div class="mt-4">
            <a href="/admin/dashboard-settings" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-500 rounded-lg shadow transition">
                Go to Dashboard Settings
            </a>
        </div>
    @endif
</div>
