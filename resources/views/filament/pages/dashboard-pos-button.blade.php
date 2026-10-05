<div class="mb-4">
    <a href="{{ url('/pos') }}"
       id="dashboard-open-pos-button"
       class="flex items-center justify-between w-full p-4 rounded-xl text-white shadow-lg transition-all duration-200 transform hover:-translate-y-0.5"
       style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 50%, #06b6d4 100%);">
        <div class="flex items-center space-x-3 sm:space-x-4">
            <div class="p-2 sm:p-3 bg-white/20 backdrop-blur-sm rounded-lg">
                <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-base sm:text-lg font-bold tracking-tight">Open POS / New Sale</div>
                <div class="text-xs sm:text-sm text-blue-100">Launch point-of-sale terminal to create a fast invoice</div>
            </div>
        </div>
        <div class="flex items-center space-x-2 bg-white/20 hover:bg-white/30 backdrop-blur-sm px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg font-semibold text-xs sm:text-sm transition">
            <span>Launch POS</span>
            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
            </svg>
        </div>
    </a>
</div>
