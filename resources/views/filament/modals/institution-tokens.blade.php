<div class="space-y-4">
    @php
        $tokens = $institution->tokens()->latest()->get();
    @endphp

    @if($tokens->isEmpty())
        <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-200 dark:border-gray-700">
            <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
            <p class="font-medium text-gray-700 dark:text-gray-300">Belum Ada Token Sanctum</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Klik tombol <strong>"Buat Token"</strong> untuk menerbitkan token baru bagi operator madrasah.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300 divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800/70 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Nama Token</th>
                        <th class="px-4 py-3">Hak Akses (Abilities)</th>
                        <th class="px-4 py-3">Terakhir Dipakai</th>
                        <th class="px-4 py-3">Diterbitkan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                    @foreach($tokens as $tok)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                {{ $tok->name }}
                            </td>
                            <td class="px-4 py-3">
                                @foreach($tok->abilities ?? [] as $ability)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200/50 dark:border-amber-700/50">
                                        {{ $ability }}
                                    </span>
                                @endforeach
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                {{ $tok->last_used_at ? $tok->last_used_at->diffForHumans() : 'Belum pernah' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                {{ $tok->created_at ? $tok->created_at->format('d M Y H:i') : '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            * Demi keamanan standar keamanan token Laravel Sanctum, plain string token hanya ditampilkan sekali saat dibuat dan langsung disalin otomatis ke clipboard.
        </p>
    @endif
</div>
