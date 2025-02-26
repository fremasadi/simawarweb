<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SidebarBarcode extends Widget
{
    protected static string $view = 'filament.widgets.sidebar-barcode';

    protected int | string | array $columnSpan = 'full';

    public function getBarcode()
    {
        $user = Auth::user();
        return QrCode::size(100)->generate($user->id); // Bisa diganti dengan data lain
    }
}
