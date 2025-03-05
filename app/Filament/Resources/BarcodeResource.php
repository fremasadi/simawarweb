<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BarcodeResource\Pages;
use App\Filament\Resources\BarcodeResource\RelationManagers;
use App\Models\Barcode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Filament\Tables\Columns\ViewColumn;



// class BarcodeResource extends Resource
// {
//     protected static ?string $model = Barcode::class;

//     protected static ?string $navigationIcon = 'heroicon-o-qr-code';

//     protected static ?string $navigationLabel = 'Barcode Absensi';

//     public static function getModelLabel(): string
//     {
//         return 'Barcode Absensi';
//     }

//     public static function getPluralModelLabel(): string
//     {
//         return 'Barcode Absensi';
//     }


//     public static function getNavigationGroup(): ?string
//     {
//         return 'Manajemen Absensi';
//     }


//     public static function table(Table $table): Table
//     {
//         return $table
//             ->columns([
//                 ViewColumn::make('qr_code')
//                 ->label('QR Code')
//                 ->view('filament.widgets.qr-code-column')
//                 ->alignCenter()
//                 ->extraAttributes([
//                     'class' => 'text-center',
//                 ])

//             ]);
//     }


//     public static function getRelations(): array
//     {
//         return [
//             //
//         ];
//     }

//     public static function getPages(): array
//     {
//         return [
//             'index' => Pages\ListBarcodes::route('/'),

//         ];
//     }
// }
