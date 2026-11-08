{{-- Горим солих товч: light үед сар, dark үед нар харагдана --}}
<button type="button" onclick="toggleTheme()" title="Харанхуй / гэрэлтэй горим" aria-label="Харанхуй / гэрэлтэй горим"
        class="{{ $class ?? 'rounded-lg border border-slate-200 p-2 text-slate-600 transition hover:bg-slate-100' }}">
    <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg>
    <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
</button>
