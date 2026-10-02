<?php

namespace Src\Setting\Models;

use Illuminate\Database\Eloquent\Model;

class StorageSetting extends Model
{
    protected $fillable = ['image_directory', 'audio_directory'];

    /**
     * Pushes this row's values into the services.media_storage config, so
     * MediaContentController and ProcessMediaContentAudio store new uploads
     * under the admin-edited folders without themselves knowing settings are
     * stored in the database. Existing files already on disk aren't moved.
     */
    public function applyToConfig(): void
    {
        config([
            'services.media_storage.image_directory' => $this->image_directory,
            'services.media_storage.audio_directory' => $this->audio_directory,
        ]);
    }
}
