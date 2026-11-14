@include('errors.layout', [
    'code' => 403,
    'icon' => 'shield-lock',
    'tone' => 'red',
    'title' => 'Akses ditolak',
    'message' => $exception?->getMessage() ?: 'Anda tidak memiliki akses ke fitur ini.',
])
