<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Filament\Support\AssetFormFields;
use Filament\Schemas\Schema;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(AssetFormFields::fields())->columns(1);
    }
}
